import { useState } from 'react';
import { KeyboardAvoidingView, Platform, ScrollView, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { ApiError, errorMessage } from '@/api/errors';
import { useAuth } from '@/auth/auth-provider';
import { BrandTitle } from '@/components/brand-title';
import { Button } from '@/components/button';
import { icons } from '@/components/icons';
import { ErrorBanner } from '@/components/screen-state';
import { TextField } from '@/components/text-field';
import { colors, fontSize, fonts, spacing } from '@/theme';

export default function LoginScreen() {
  const { state, signIn } = useAuth();
  const savedServerUrl = state.status === 'signedOut' ? state.serverUrl : null;

  const [serverUrl, setServerUrl] = useState(savedServerUrl ?? process.env.EXPO_PUBLIC_API_URL ?? '');
  const [loginName, setLoginName] = useState('');
  const [password, setPassword] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<unknown>(null);

  const canSubmit = serverUrl.trim() !== '' && loginName.trim() !== '' && password !== '';
  const fieldError = (field: string) => (error instanceof ApiError ? error.firstFieldError(field) : undefined);
  const generalError = error && !fieldError('login') && !fieldError('password') ? errorMessage(error) : null;

  const submit = async () => {
    setSubmitting(true);
    setError(null);

    try {
      await signIn(serverUrl, loginName, password);
    } catch (caught) {
      setError(caught);
      setSubmitting(false);
    }
  };

  return (
    <SafeAreaView style={styles.safeArea}>
      <KeyboardAvoidingView style={styles.flex} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
        <ScrollView contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
          <View style={styles.brand}>
            <BrandTitle height={110} />
            <Text style={styles.tagline}>Şantiye ve hafriyat hattını tek noktadan yönetin.</Text>
            <View style={styles.rule} />
          </View>

          <View style={styles.header}>
            <Text style={styles.title}>Giriş yap</Text>
            <Text style={styles.subtitle}>Yetkili hesabınızla devam edin.</Text>
          </View>

          {generalError ? <ErrorBanner message={generalError} /> : null}

          <TextField
            appearance="dark"
            label="Sunucu adresi"
            value={serverUrl}
            onChangeText={setServerUrl}
            placeholder="ornek: 192.168.1.10:8010"
            autoCapitalize="none"
            autoCorrect={false}
            keyboardType="url"
          />
          <TextField
            appearance="dark"
            label="Kullanıcı adı / E-posta"
            value={loginName}
            onChangeText={setLoginName}
            placeholder="kullanıcı adı veya e-posta"
            error={fieldError('login')}
            autoCapitalize="none"
            autoCorrect={false}
            autoComplete="username"
          />
          <TextField
            appearance="dark"
            label="Şifre"
            value={password}
            onChangeText={setPassword}
            placeholder="••••••••"
            error={fieldError('password')}
            secureTextEntry
            autoComplete="password"
            returnKeyType="go"
            onSubmitEditing={() => canSubmit && void submit()}
          />

          <Button title="Giriş yap" icon={icons.signIn} onPress={() => void submit()} loading={submitting} disabled={!canSubmit} />

          <Text style={styles.footer}>HafLine · Hafriyat Yönetim</Text>
        </ScrollView>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safeArea: {
    flex: 1,
    backgroundColor: colors.steel,
  },
  flex: {
    flex: 1,
  },
  content: {
    flexGrow: 1,
    justifyContent: 'center',
    gap: spacing.md,
    padding: spacing.lg,
  },
  brand: {
    alignItems: 'center',
    gap: spacing.sm,
    marginBottom: spacing.md,
  },
  tagline: {
    fontSize: fontSize.body,
    fontFamily: fonts.medium,
    color: 'rgba(230, 235, 232, 0.7)',
    textAlign: 'center',
  },
  rule: {
    width: 120,
    height: 2,
    backgroundColor: colors.brandLine,
  },
  header: {
    gap: spacing.xs,
    paddingTop: spacing.md,
    borderTopWidth: 1,
    borderTopColor: 'rgba(196, 137, 46, 0.4)',
  },
  title: {
    fontSize: 24,
    fontFamily: fonts.extrabold,
    letterSpacing: -0.4,
    color: colors.primaryText,
  },
  subtitle: {
    fontSize: fontSize.small,
    fontFamily: fonts.medium,
    color: 'rgba(230, 235, 232, 0.7)',
  },
  footer: {
    marginTop: spacing.md,
    paddingTop: spacing.md,
    borderTopWidth: 1,
    borderTopColor: 'rgba(255, 255, 255, 0.1)',
    fontSize: 11,
    fontFamily: fonts.semibold,
    letterSpacing: 1.5,
    textTransform: 'uppercase',
    color: 'rgba(230, 235, 232, 0.35)',
    textAlign: 'center',
  },
});
