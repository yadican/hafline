import { ScrollView, StyleSheet, Text, View } from 'react-native';

import { useAuth, useSignedInUser } from '@/auth/auth-provider';
import { Button } from '@/components/button';
import { icons } from '@/components/icons';
import { colors, fontSize, fonts, radius, spacing } from '@/theme';

export default function AccountScreen() {
  const user = useSignedInUser();
  const { signOut } = useAuth();

  return (
    <ScrollView contentContainerStyle={styles.content}>
      <View style={styles.card}>
        <Text style={styles.name}>{user.name}</Text>
      </View>
      <Button title="Çıkış yap" variant="danger" icon={icons.signOut} onPress={() => void signOut()} />
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  content: {
    gap: spacing.md,
    padding: spacing.lg,
  },
  card: {
    padding: spacing.md,
    borderRadius: radius,
    borderWidth: 1,
    borderLeftWidth: 4,
    borderColor: colors.border,
    borderLeftColor: colors.brandLine,
    backgroundColor: colors.surface,
  },
  name: {
    fontSize: fontSize.title,
    fontFamily: fonts.extrabold,
    color: colors.text,
  },
});
