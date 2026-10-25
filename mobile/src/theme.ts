export const colors = {
  primary: '#1F6FD1',
  primaryText: '#FFFFFF',
  background: '#F2F4F7',
  surface: '#FFFFFF',
  text: '#101828',
  muted: '#667085',
  border: '#D0D5DD',
  selected: '#E8F1FC',
  danger: '#D92D20',
  dangerSurface: '#FEF3F2',
  success: '#067647',
  successSurface: '#ECFDF3',
  warning: '#B54708',
  warningSurface: '#FFFAEB',
} as const;

export const spacing = {
  xs: 4,
  sm: 8,
  md: 16,
  lg: 24,
  xl: 32,
} as const;

export const radius = 10;

/** Large enough to hit with work gloves on a handheld terminal. */
export const touchHeight = 54;

export const fontSize = {
  small: 14,
  body: 17,
  title: 22,
} as const;
