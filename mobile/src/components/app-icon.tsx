import { Image } from 'expo-image';
import { SymbolView } from 'expo-symbols';
import type { ColorValue } from 'react-native';

import { isSvgIcon, type AppIconName } from './icons';

type AppIconProps = {
  name: AppIconName;
  color: ColorValue;
  size: number;
};

/** Renders a symbol or one of our own SVG icons with the same name / colour / size contract. */
export function AppIcon({ name, color, size }: AppIconProps) {
  if (isSvgIcon(name)) {
    return (
      <Image
        source={name.svg}
        svgVariables={{ '--icon': String(color) }}
        style={{ width: size, height: size }}
        contentFit="contain"
      />
    );
  }

  return <SymbolView name={name} tintColor={color} size={size} />;
}
