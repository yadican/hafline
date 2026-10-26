import type { SymbolViewProps } from 'expo-symbols';

export type IconName = SymbolViewProps['name'];

/** SF Symbols on iOS, Material Symbols on Android / web. */
export const icons = {
  hauls: { ios: 'truck.box', android: 'local_shipping', web: 'local_shipping' },
  machineWorks: { ios: 'hammer', android: 'construction', web: 'construction' },
  fuelReceipts: { ios: 'fuelpump', android: 'local_gas_station', web: 'local_gas_station' },
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
} as const satisfies Record<string, IconName>;
