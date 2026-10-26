/** Keeps digits and a single decimal comma while the user types; "4.5" is shown as "4,5". */
export function decimalInput(text: string, maxDecimals = 2): string {
  const [whole, ...rest] = text.replace(/\./g, ',').replace(/[^\d,]/g, '').split(',');

  if (rest.length === 0) {
    return whole;
  }

  return `${whole},${rest.join('').slice(0, maxDecimals)}`;
}

export function integerInput(text: string): string {
  return text.replace(/\D/g, '');
}

/** Parses "4,5" or "4.5"; blank or invalid input gives null. */
export function parseDecimal(text: string): number | null {
  const trimmed = text.trim().replace(',', '.');

  if (trimmed === '' || trimmed === '.') {
    return null;
  }

  const value = Number(trimmed);

  return Number.isFinite(value) ? value : null;
}

export function parseInteger(text: string): number | null {
  const trimmed = text.trim();

  return trimmed === '' ? null : Number.parseInt(trimmed, 10);
}

/** Turns an API decimal string such as "1500.00" into the form's "1500,00". */
export function decimalForInput(value: string | number | null | undefined): string {
  return value === null || value === undefined ? '' : String(value).replace('.', ',');
}

export function formatMoney(value: string | number): string {
  return `${Number(value).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ₺`;
}

export function formatNumber(value: string | number): string {
  return Number(value).toLocaleString('tr-TR', { maximumFractionDigits: 2 });
}
