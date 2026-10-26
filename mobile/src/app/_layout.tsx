import {
  Manrope_400Regular,
  Manrope_500Medium,
  Manrope_600SemiBold,
  Manrope_700Bold,
  Manrope_800ExtraBold,
  useFonts,
} from '@expo-google-fonts/manrope';
import { QueryClientProvider } from '@tanstack/react-query';
import { Stack } from 'expo-router';
import * as SplashScreen from 'expo-splash-screen';
import { StatusBar } from 'expo-status-bar';
import { useEffect } from 'react';
import { StyleSheet, View } from 'react-native';

import { AuthProvider, useAuth } from '@/auth/auth-provider';
import { BrandTitle } from '@/components/brand-title';
import { LoadingState } from '@/components/screen-state';
import { queryClient } from '@/query-client';
import { colors, fonts } from '@/theme';

void SplashScreen.preventAutoHideAsync();

export default function RootLayout() {
  const [fontsLoaded, fontError] = useFonts({
    Manrope_400Regular,
    Manrope_500Medium,
    Manrope_600SemiBold,
    Manrope_700Bold,
    Manrope_800ExtraBold,
  });
  const fontsReady = fontsLoaded || fontError !== null;

  useEffect(() => {
    if (fontsReady) {
      void SplashScreen.hideAsync();
    }
  }, [fontsReady]);

  if (!fontsReady) {
    return null;
  }

  return (
    <QueryClientProvider client={queryClient}>
      <AuthProvider>
        <StatusBar style="light" />
        <RootNavigator />
      </AuthProvider>
    </QueryClientProvider>
  );
}

function HeaderBackground() {
  return <View style={styles.header} />;
}

function RootNavigator() {
  const { state } = useAuth();

  if (state.status === 'loading') {
    return <LoadingState />;
  }

  const signedIn = state.status === 'signedIn';

  return (
    <Stack
      screenOptions={{
        headerTitleAlign: 'center',
        headerBackground: HeaderBackground,
        headerTintColor: colors.steelText,
        headerTitleStyle: { fontFamily: fonts.bold, color: colors.primaryText },
        headerBackButtonDisplayMode: 'minimal',
        contentStyle: { backgroundColor: colors.background },
      }}
    >
      <Stack.Protected guard={signedIn}>
        <Stack.Screen name="index" options={{ title: 'Hafline Saha', headerTitle: () => <BrandTitle /> }} />
        <Stack.Screen name="session" options={{ title: 'Oturum Seçimi' }} />
        <Stack.Screen name="scan" options={{ title: 'Plaka Tara' }} />
        <Stack.Screen name="ticket/[id]" options={{ title: 'Fiş' }} />
      </Stack.Protected>
      <Stack.Protected guard={!signedIn}>
        <Stack.Screen name="login" options={{ headerShown: false }} />
      </Stack.Protected>
    </Stack>
  );
}

const styles = StyleSheet.create({
  header: {
    flex: 1,
    backgroundColor: colors.steel,
    borderBottomWidth: 2,
    borderBottomColor: colors.brandLine,
  },
});
