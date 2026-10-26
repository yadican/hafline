package expo.modules.sunmiprinter

import com.sunmi.peripheral.printer.InnerPrinterCallback
import com.sunmi.peripheral.printer.InnerPrinterException
import com.sunmi.peripheral.printer.InnerPrinterManager
import com.sunmi.peripheral.printer.InnerResultCallback
import com.sunmi.peripheral.printer.SunmiPrinterService
import com.sunmi.peripheral.printer.WoyouConsts
import expo.modules.kotlin.exception.CodedException
import expo.modules.kotlin.exception.Exceptions
import expo.modules.kotlin.functions.Coroutine
import expo.modules.kotlin.modules.Module
import expo.modules.kotlin.modules.ModuleDefinition
import expo.modules.kotlin.records.Field
import expo.modules.kotlin.records.Record
import kotlinx.coroutines.CompletableDeferred
import kotlinx.coroutines.suspendCancellableCoroutine
import kotlinx.coroutines.withTimeoutOrNull
import kotlin.coroutines.resume
import kotlin.coroutines.resumeWithException

private const val CONNECT_TIMEOUT_MS = 3_000L
private const val PRINT_TIMEOUT_MS = 30_000L

private const val STATE_READY = 1
private const val STATE_OUT_OF_PAPER = 4
private const val STATE_OVERHEATED = 5
private const val PRINT_RESULT_SUCCESS = 0

private const val ALIGN_LEFT = 0
private const val ALIGN_CENTER = 1
private const val ALIGN_RIGHT = 2

/** 58 mm head is 384 dots; at 24 px a Latin glyph is 12 dots, so 32 characters fill a line. */
private const val TEXT_SIZE = 24f
private const val TICKET_NO_SIZE = 30f
private val DIVIDER = "-".repeat(32) + "\n"
private val COLUMN_WEIGHTS = intArrayOf(2, 3)
private val COLUMN_ALIGNS = intArrayOf(ALIGN_LEFT, ALIGN_RIGHT)

private const val QR_MODULE_SIZE = 6
private const val QR_ERROR_LEVEL_M = 1
private const val FEED_LINES = 4

class ReceiptLine : Record {
  @Field
  val label: String = ""

  @Field
  val value: String = ""
}

class Receipt : Record {
  @Field
  val title: String = ""

  @Field
  val ticketNo: String = ""

  @Field
  val lines: List<ReceiptLine> = emptyList()

  @Field
  val qrPayload: String = ""
}

internal class PrinterUnavailableException : CodedException("Sunmi printer service is not available")

internal class OutOfPaperException : CodedException("Printer is out of paper")

internal class OverheatedException : CodedException("Printer is overheated")

internal class PrinterNotReadyException(state: Int) : CodedException("Printer is not ready (state $state)")

internal class PrintFailedException(reason: String?) : CodedException("Printing failed: $reason")

class SunmiPrinterModule : Module() {
  private var service: SunmiPrinterService? = null
  private var connection: CompletableDeferred<SunmiPrinterService?>? = null

  private val callback = object : InnerPrinterCallback() {
    override fun onConnected(printer: SunmiPrinterService) {
      synchronized(this@SunmiPrinterModule) {
        service = printer
        connection?.complete(printer)
        connection = null
      }
    }

    override fun onDisconnected() {
      synchronized(this@SunmiPrinterModule) {
        service = null
      }
    }
  }

  override fun definition() = ModuleDefinition {
    Name("SunmiPrinter")

    OnDestroy {
      val context = appContext.reactContext ?: return@OnDestroy
      runCatching { InnerPrinterManager.getInstance().unBindService(context, callback) }
    }

    AsyncFunction("isAvailableAsync") Coroutine { ->
      val printer = connect() ?: return@Coroutine false
      runCatching { InnerPrinterManager.getInstance().hasPrinter(printer) }.getOrDefault(false)
    }

    AsyncFunction("printReceiptAsync") Coroutine { receipt: Receipt ->
      val printer = connect() ?: throw PrinterUnavailableException()
      ensureReady(printer)
      write(printer, receipt)
      withTimeoutOrNull(PRINT_TIMEOUT_MS) { commit(printer) } ?: throw PrintFailedException("timeout")
    }
  }

  /** Binds lazily; non-Sunmi devices have no printer service, so binding fails and this resolves to null. */
  private suspend fun connect(): SunmiPrinterService? {
    val pending = synchronized(this) {
      service?.let { return it }
      connection ?: CompletableDeferred<SunmiPrinterService?>().also { bind(it) }
    }

    return withTimeoutOrNull(CONNECT_TIMEOUT_MS) { pending.await() }
  }

  private fun bind(pending: CompletableDeferred<SunmiPrinterService?>) {
    val context = appContext.reactContext ?: throw Exceptions.ReactContextLost()
    val bound = try {
      InnerPrinterManager.getInstance().bindService(context, callback)
    } catch (error: InnerPrinterException) {
      false
    }

    if (bound) {
      connection = pending
    } else {
      pending.complete(null)
    }
  }

  private fun ensureReady(printer: SunmiPrinterService) {
    when (val state = printer.updatePrinterState()) {
      STATE_READY -> Unit
      STATE_OUT_OF_PAPER -> throw OutOfPaperException()
      STATE_OVERHEATED -> throw OverheatedException()
      else -> throw PrinterNotReadyException(state)
    }
  }

  /** Queues the receipt in a transaction so the printer reports whether it actually came out. */
  private fun write(printer: SunmiPrinterService, receipt: Receipt) {
    printer.enterPrinterBuffer(true)
    printer.printerInit(null)

    printer.setAlignment(ALIGN_CENTER, null)
    printer.setPrinterStyle(WoyouConsts.ENABLE_BOLD, WoyouConsts.ENABLE)
    printer.setFontSize(TEXT_SIZE, null)
    printer.printText("${receipt.title}\n", null)
    printer.setFontSize(TICKET_NO_SIZE, null)
    printer.printText("${receipt.ticketNo}\n", null)
    printer.setPrinterStyle(WoyouConsts.ENABLE_BOLD, WoyouConsts.DISABLE)
    printer.setFontSize(TEXT_SIZE, null)
    printer.printText(DIVIDER, null)

    for (line in receipt.lines) {
      printer.printColumnsString(arrayOf(line.label, line.value), COLUMN_WEIGHTS, COLUMN_ALIGNS, null)
    }

    printer.printText(DIVIDER, null)
    printer.setAlignment(ALIGN_CENTER, null)
    printer.printQRCode(receipt.qrPayload, QR_MODULE_SIZE, QR_ERROR_LEVEL_M, null)
    printer.lineWrap(FEED_LINES, null)
  }

  private suspend fun commit(printer: SunmiPrinterService): Unit = suspendCancellableCoroutine<Unit> { continuation ->
    printer.exitPrinterBufferWithCallback(
      true,
      object : InnerResultCallback() {
        override fun onRunResult(isSuccess: Boolean) = Unit

        override fun onReturnString(result: String?) = Unit

        override fun onRaiseException(code: Int, msg: String?) {
          if (continuation.isActive) {
            continuation.resumeWithException(PrintFailedException(msg))
          }
        }

        override fun onPrintResult(code: Int, msg: String?) {
          if (!continuation.isActive) {
            return
          }

          if (code == PRINT_RESULT_SUCCESS) {
            continuation.resume(Unit)
          } else {
            continuation.resumeWithException(PrintFailedException(msg))
          }
        }
      },
    )
  }
}
