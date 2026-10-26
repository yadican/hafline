import createQrCode from 'qrcode-generator';

export type ReceiptLine = { label: string; value: string };

export type Receipt = {
  title: string;
  ticketNo: string;
  lines: ReceiptLine[];
  qrPayload: string;
};

const PAPER_WIDTH_MM = 58;
const HEADER_MM = 24;
const LINE_MM = 6;
const QR_BLOCK_MM = 44;

/** expo-print measures pages in points (72 PPI). */
const POINTS_PER_MM = 72 / 25.4;

/** Height grows with the line count so the receipt prints as one strip instead of breaking into pages. */
function receiptHeightMm(receipt: Receipt): number {
  return HEADER_MM + receipt.lines.length * LINE_MM + QR_BLOCK_MM;
}

export function receiptPageSize(receipt: Receipt): { width: number; height: number } {
  return {
    width: Math.round(PAPER_WIDTH_MM * POINTS_PER_MM),
    height: Math.ceil(receiptHeightMm(receipt) * POINTS_PER_MM),
  };
}

export function receiptHtml(receipt: Receipt): string {
  const rows = receipt.lines
    .map((line) => `<tr><th>${escapeHtml(line.label)}</th><td>${escapeHtml(line.value)}</td></tr>`)
    .join('');

  return `<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<style>
  @page { size: ${PAPER_WIDTH_MM}mm ${receiptHeightMm(receipt)}mm; margin: 0; }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { width: ${PAPER_WIDTH_MM}mm; padding: 3mm 5mm; font-family: Helvetica, Arial, sans-serif; font-size: 8pt; color: #000; }
  h1 { font-size: 9pt; letter-spacing: 0.5pt; text-align: center; }
  .ticket-no { margin-top: 1mm; padding-bottom: 2mm; border-bottom: 1px dashed #000; font-size: 11pt; font-weight: 800; text-align: center; word-break: break-all; }
  table { width: 100%; margin-top: 2mm; border-collapse: collapse; }
  th, td { padding: 0.6mm 0; vertical-align: top; }
  th { width: 42%; font-weight: 400; text-align: left; }
  td { font-weight: 700; text-align: right; word-break: break-word; }
  .qr { margin-top: 3mm; padding-top: 3mm; border-top: 1px dashed #000; text-align: center; }
  .qr svg { width: 32mm; height: 32mm; }
</style>
</head>
<body>
  <h1>${escapeHtml(receipt.title)}</h1>
  <div class="ticket-no">${escapeHtml(receipt.ticketNo)}</div>
  <table>${rows}</table>
  <div class="qr">${qrSvg(receipt.qrPayload)}</div>
</body>
</html>`;
}

function qrSvg(payload: string): string {
  const code = createQrCode(0, 'M');
  code.addData(payload);
  code.make();

  return code.createSvgTag({ margin: 0, scalable: true });
}

function escapeHtml(text: string): string {
  return text
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}
