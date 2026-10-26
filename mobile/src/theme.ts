/** Keep in sync with the back office palette (--ui-* in htdocs/resources/css/panel-ui.css). */
export const colors = {
  steel: '#0e1114',
  steelText: '#edd9b0',
  brandLine: '#c4892e',

  primary: '#b0782a',
  primaryDark: '#8c5f20',
  primaryText: '#FFFFFF',

  create: '#4a7c59',
  createDark: '#3a6347',
  edit: '#3b82c4',
  editDark: '#2f6aa3',

  background: '#f3f4f6',
  surface: '#FFFFFF',
  surfaceMuted: '#f8f9fb',
  text: '#2a2e32',
  muted: '#6a6560',
  border: '#d5d8de',
  borderSoft: '#e6e8ed',
  inputBorder: '#cfc7bb',
  placeholder: '#a59e95',
  radioBorder: '#bfb3a2',
  selected: '#f3ebe0',

  danger: '#8b3a3a',
  dangerDark: '#6f2e2e',
  dangerText: '#6e3434',
  dangerSurface: '#fbeaea',
  dangerBorder: '#e2b3b3',
  success: '#3f5c38',
  successBorder: '#d5e0d2',
  successSurface: '#f4f7f2',
  warning: '#7a4e08',
  warningBorder: '#e4c56a',
  warningSurface: '#fbf3e0',
} as const;

/** One light background + matching icon colour per ticket type, so types read at a glance without labels. */
export const ticketTones = {
  haul: { background: '#fdf0de', icon: '#b86a12' },
  machine_work: { background: '#e4eefa', icon: '#2f6aa3' },
  fuel_receipt: { background: '#e3f3e8', icon: '#3a7d50' },
} as const;

export type TicketType = keyof typeof ticketTones;

/** Manrope, loaded per weight so Android 7 (Sunmi V2 Pro) gets real faces instead of synthesized bold. */
export const fonts = {
  regular: 'Manrope_400Regular',
  medium: 'Manrope_500Medium',
  semibold: 'Manrope_600SemiBold',
  bold: 'Manrope_700Bold',
  extrabold: 'Manrope_800ExtraBold',
} as const;

export const spacing = {
  xs: 4,
  sm: 8,
  md: 16,
  lg: 24,
  xl: 32,
} as const;

export const radius = 8;

/** Large enough to hit with work gloves on a handheld terminal. */
export const touchHeight = 54;

export const fontSize = {
  small: 14,
  body: 16,
  title: 22,
} as const;
