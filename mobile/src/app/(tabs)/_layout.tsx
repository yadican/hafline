import { router, Tabs } from 'expo-router';
import { SymbolView } from 'expo-symbols';
import { Pressable, StyleSheet, type ColorValue } from 'react-native';

import { HeaderBackground } from '@/components/header-background';
import { icons, type IconName } from '@/components/icons';
import { colors, fontSize, fonts, spacing } from '@/theme';

function tabIcon(name: IconName) {
  return function TabIcon({ color, size }: { color: ColorValue; size: number }) {
    return <SymbolView name={name} tintColor={color} size={size} />;
  };
}

const HaulsIcon = tabIcon(icons.hauls);
const MachineWorksIcon = tabIcon(icons.machineWorks);
const FuelReceiptsIcon = tabIcon(icons.fuelReceipts);

function AccountButton() {
  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel="Hesap"
      onPress={() => router.push('/account')}
      hitSlop={12}
      style={styles.account}
    >
      <SymbolView name={icons.account} tintColor={colors.steelText} size={28} />
    </Pressable>
  );
}

export default function TabLayout() {
  return (
    <Tabs
      screenOptions={{
        headerTitleAlign: 'center',
        headerBackground: HeaderBackground,
        headerTitleStyle: { fontFamily: fonts.bold, color: colors.primaryText },
        headerRight: AccountButton,
        sceneStyle: { backgroundColor: colors.background },
        tabBarActiveTintColor: colors.primaryDark,
        tabBarInactiveTintColor: colors.muted,
        tabBarLabelStyle: { fontSize: fontSize.small, fontFamily: fonts.bold },
        tabBarStyle: { backgroundColor: colors.surface, borderTopColor: colors.border },
      }}
    >
      <Tabs.Screen
        name="hauls"
        options={{ title: 'Taşıma Fişleri', tabBarLabel: 'Taşıma', tabBarIcon: HaulsIcon }}
      />
      <Tabs.Screen
        name="machine-works"
        options={{ title: 'Makine Fişleri', tabBarLabel: 'Makine', tabBarIcon: MachineWorksIcon }}
      />
      <Tabs.Screen
        name="fuel-receipts"
        options={{ title: 'Yakıt Fişleri', tabBarLabel: 'Yakıt', tabBarIcon: FuelReceiptsIcon }}
      />
    </Tabs>
  );
}

const styles = StyleSheet.create({
  account: {
    marginRight: spacing.md,
  },
});
