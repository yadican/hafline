export type SunmiReceipt = {
  title: string;
  ticketNo: string;
  lines: { label: string; value: string }[];
  qrPayload: string;
};

/** Rejection codes thrown by `printReceiptAsync`. */
export type SunmiPrintErrorCode =
  | 'ERR_PRINTER_UNAVAILABLE'
  | 'ERR_OUT_OF_PAPER'
  | 'ERR_OVERHEATED'
  | 'ERR_PRINTER_NOT_READY'
  | 'ERR_PRINT_FAILED';
