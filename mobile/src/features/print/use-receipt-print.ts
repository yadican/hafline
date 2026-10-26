import { useQuery } from '@tanstack/react-query';
import { File, Paths } from 'expo-file-system';
import * as Print from 'expo-print';
import * as Sharing from 'expo-sharing';
import { useState } from 'react';

import SunmiPrinter, { type SunmiPrintErrorCode } from '../../../modules/sunmi-printer';

import { receiptHtml, receiptPageSize, type Receipt } from './receipt';

type ReceiptAction = 'thermal' | 'print' | 'share';

const PRINT_FAILED_MESSAGE = 'Fiş yazdırılamadı. Yazıcı bağlantısını kontrol edip tekrar deneyin.';
const SHARE_FAILED_MESSAGE = 'Fiş PDF olarak paylaşılamadı.';
const SHARE_UNAVAILABLE_MESSAGE = 'Bu cihazda paylaşım desteklenmiyor.';

const THERMAL_ERROR_MESSAGES: Record<SunmiPrintErrorCode, string> = {
  ERR_PRINTER_UNAVAILABLE: 'Cihazın yazıcısına bağlanılamadı.',
  ERR_OUT_OF_PAPER: 'Yazıcıda kâğıt bitti. Yeni rulo takıp tekrar deneyin.',
  ERR_OVERHEATED: 'Yazıcı ısındı. Biraz bekleyip tekrar deneyin.',
  ERR_PRINTER_NOT_READY: 'Yazıcı hazır değil. Biraz bekleyip tekrar deneyin.',
  ERR_PRINT_FAILED: PRINT_FAILED_MESSAGE,
};

/** iOS rejects with this code when the print sheet is closed without printing; that is not a failure. */
const PRINT_CANCELLED_CODE = 'ERR_PRINT_INCOMPLETE';

/** True only on Sunmi terminals running a build that includes the native printer module. */
export function useThermalPrinterAvailable(): boolean {
  const available = useQuery({
    queryKey: ['print', 'thermal-available'],
    queryFn: () => SunmiPrinter?.isAvailableAsync() ?? false,
    enabled: SunmiPrinter !== null,
    staleTime: Infinity,
  });

  return available.data === true;
}

export function useReceiptPrint(receipt: Receipt) {
  const [pending, setPending] = useState<ReceiptAction | null>(null);
  const [error, setError] = useState<string | null>(null);

  const run = async (action: ReceiptAction, task: () => Promise<void>, failure: (cause: unknown) => string) => {
    setPending(action);
    setError(null);

    try {
      await task();
    } catch (cause) {
      if (errorCode(cause) !== PRINT_CANCELLED_CODE) {
        setError(failure(cause));
      }
    } finally {
      setPending(null);
    }
  };

  const printThermal = () =>
    run(
      'thermal',
      async () => {
        if (!SunmiPrinter) {
          throw new Error('Sunmi printer module is not in this build');
        }

        await SunmiPrinter.printReceiptAsync(receipt);
      },
      (cause) => THERMAL_ERROR_MESSAGES[errorCode(cause) as SunmiPrintErrorCode] ?? PRINT_FAILED_MESSAGE,
    );

  const print = () =>
    run(
      'print',
      () => Print.printAsync({ html: receiptHtml(receipt), ...receiptPageSize(receipt) }),
      () => PRINT_FAILED_MESSAGE,
    );

  const share = async () => {
    if (!(await Sharing.isAvailableAsync())) {
      setError(SHARE_UNAVAILABLE_MESSAGE);

      return;
    }

    await run(
      'share',
      async () => {
        const pdf = await receiptPdf(receipt);
        await Sharing.shareAsync(pdf.uri, { mimeType: 'application/pdf', UTI: 'com.adobe.pdf', dialogTitle: receipt.ticketNo });
      },
      () => SHARE_FAILED_MESSAGE,
    );
  };

  return { printThermal, print, share, pending, error };
}

/** Renames the cached PDF after the ticket number so it is recognisable once shared (WhatsApp, mail, ...). */
async function receiptPdf(receipt: Receipt): Promise<File> {
  const { uri } = await Print.printToFileAsync({ html: receiptHtml(receipt), ...receiptPageSize(receipt) });
  const pdf = new File(uri);
  await pdf.move(new File(Paths.cache, `${receipt.ticketNo.replace(/[^\w-]/g, '-')}.pdf`), { overwrite: true });

  return pdf;
}

function errorCode(error: unknown): string | undefined {
  return typeof error === 'object' && error !== null && 'code' in error && typeof error.code === 'string'
    ? error.code
    : undefined;
}
