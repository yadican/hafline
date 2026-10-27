import type { SymbolViewProps } from 'expo-symbols';

export type IconName = SymbolViewProps['name'];

/** Drawn in-house where the symbol sets have no match; tinted through the SVG's `--icon` variable. */
export type SvgIcon = { svg: number };

export type AppIconName = IconName | SvgIcon;

export function isSvgIcon(name: AppIconName): name is SvgIcon {
  return typeof name === 'object' && 'svg' in name;
}

/** SF Symbols on iOS, Material Symbols on Android / web; `svg` entries are the same on every platform. */
export const icons = {
  dashboard: { ios: 'square.grid.2x2', android: 'dashboard', web: 'dashboard' },
  home: { ios: 'house', android: 'home', web: 'home' },
  hauls: { svg: require('@/assets/icons/dump-truck.svg') },
  machineWorks: { svg: require('@/assets/icons/excavator.svg') },
  fuelReceipts: { svg: require('@/assets/icons/fuel-pump.svg') },
  account: { ios: 'person.crop.circle', android: 'account_circle', web: 'account_circle' },

  add: { ios: 'plus', android: 'add', web: 'add' },
  save: { ios: 'checkmark', android: 'check', web: 'check' },
  edit: { ios: 'pencil', android: 'edit', web: 'edit' },
  delete: { ios: 'trash', android: 'delete', web: 'delete' },
  retry: { ios: 'arrow.clockwise', android: 'refresh', web: 'refresh' },
  signIn: { ios: 'arrow.right', android: 'arrow_forward', web: 'arrow_forward' },
  signOut: {
    ios: 'rectangle.portrait.and.arrow.right',
    android: 'logout',
    web: 'logout',
  },

  print: { ios: 'printer', android: 'print', web: 'print' },
  otherPrinter: { ios: 'printer.dotmatrix', android: 'local_printshop', web: 'local_printshop' },
  share: { ios: 'square.and.arrow.up', android: 'share', web: 'share' },
  camera: { ios: 'camera', android: 'photo_camera', web: 'photo_camera' },
  gallery: { ios: 'photo.on.rectangle', android: 'photo_library', web: 'photo_library' },
} as const satisfies Record<string, AppIconName>;
