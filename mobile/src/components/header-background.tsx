import { StyleSheet, View } from 'react-native';

import { colors } from '@/theme';

export function HeaderBackground() {
  return <View style={styles.header} />;
}

const styles = StyleSheet.create({
  header: {
    flex: 1,
    backgroundColor: colors.steel,
    borderBottomWidth: 2,
    borderBottomColor: colors.brandLine,
  },
});
