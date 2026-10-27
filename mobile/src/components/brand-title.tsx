import { Image } from 'expo-image';
import { StyleSheet } from 'react-native';

const LOGO = require('@/assets/images/logo-on-dark.png');

/** Logo for the dark header and login screen; the source is 800×413. */
export function BrandTitle({ height = 34 }: { height?: number }) {
  return (
    <Image
      source={LOGO}
      style={[styles.logo, { height, width: Math.round((height * 800) / 413) }]}
      contentFit="contain"
      accessibilityLabel="HafLine"
    />
  );
}

const styles = StyleSheet.create({
  logo: {
    alignSelf: 'center',
  },
});
