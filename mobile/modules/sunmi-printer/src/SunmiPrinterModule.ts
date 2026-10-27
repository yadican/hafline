import { NativeModule, requireOptionalNativeModule } from 'expo';

import type { SunmiReceipt } from './SunmiPrinter.types';

declare class SunmiPrinterModule extends NativeModule {
  isAvailableAsync(): Promise<boolean>;
  printReceiptAsync(receipt: SunmiReceipt): Promise<void>;
}

/** Null in Expo Go, on iOS and in builds made before the module was added. */
export default requireOptionalNativeModule<SunmiPrinterModule>('SunmiPrinter');
