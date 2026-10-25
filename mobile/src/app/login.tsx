import { useState } from 'react';
import { KeyboardAvoidingView, Platform, ScrollView, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { ApiError, errorMessage } from '@/api/errors';
import { useAuth } from '@/auth/auth-provider';
import { Button } from '@/components/button';
import { ErrorBanner } from '@/components/screen-state';
import { TextField } from '@/components/text-field';
import { colors, fontSize, spacing } from '@/theme';

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
          <View style={styles.header}>
            <Text style={styles.title}>Hafline Saha</Text>
            <Text style={styles.subtitle}>Devam etmek için giriş yapın</Text>
          </View>

          {generalError ? <ErrorBanner message={generalError} /> : null}

          <TextField
            label="Sunucu adresi"
            value={serverUrl}
            onChangeText={setServerUrl}
            placeholder="ornek: 192.168.1.10:8010"
            autoCapitalize="none"
            autoCorrect={false}
            keyboardType="url"
          />
          <TextField
            label="Kullanıcı adı veya e-posta"
            value={loginName}
            onChangeText={setLoginName}
            error={fieldError('login')}
            autoCapitalize="none"
            autoCorrect={false}
            autoComplete="username"
          />
          <TextField
            label="Şifre"
            value={password}
            onChangeText={setPassword}
            error={fieldError('password')}
            secureTextEntry
            autoComplete="password"
            returnKeyType="go"
            onSubmitEditing={() => canSubmit && void submit()}
          />

          <Button title="Giriş yap" onPress={() => void submit()} loading={submitting} disabled={!canSubmit} />
        </ScrollView>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safeArea: {
    flex: 1,
    backgroundColor: colors.background,
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
  header: {
    gap: spacing.xs,
    marginBottom: spacing.md,
  },
  title: {
    fontSize: 28,
    fontWeight: '700',
    color: colors.text,
  },
  subtitle: {
    fontSize: fontSize.body,
    color: colors.muted,
  },
});
