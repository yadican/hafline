import { Pressable, StyleSheet, Text, View } from 'react-native';

import { normalizePlateInput, PLATE_MAX_LENGTH } from '@/features/plate/plate';
import { colors, fontSize, fonts, radius, spacing, touchHeight } from '@/theme';

import { TextField } from './text-field';

export type PlateSuggestion = {
  plate: string;
  detail: string | null;
};

type PlateInputProps = {
  value: string;
  onChangeText: (plate: string) => void;
  error?: string;
  suggestions?: PlateSuggestion[];
};

export function PlateInput({ value, onChangeText, error, suggestions = [] }: PlateInputProps) {
  const typed = value.trim();
  const visible = suggestions.filter((suggestion) => suggestion.plate !== typed);
  const exactMatch = suggestions.some((suggestion) => suggestion.plate === typed);

  return (
    <View style={styles.container}>
      <TextField
        label="Plaka"
        value={value}
        onChangeText={(text) => onChangeText(normalizePlateInput(text))}
        placeholder="34 ABC 123"
        autoCapitalize="characters"
        autoCorrect={false}
        maxLength={PLATE_MAX_LENGTH}
        error={error}
      />
      {visible.length > 0 && !exactMatch ? (
        <View style={styles.list}>
          {visible.map((suggestion, index) => (
            <Pressable
              key={suggestion.plate}
              accessibilityRole="button"
              onPress={() => onChangeText(suggestion.plate)}
              style={({ pressed }) => [styles.row, index > 0 && styles.rowDivider, pressed && styles.rowPressed]}
            >
              <Text style={styles.plate}>{suggestion.plate}</Text>
              {suggestion.detail ? <Text style={styles.detail}>{suggestion.detail}</Text> : null}
            </Pressable>
          ))}
        </View>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    gap: spacing.xs,
  },
  list: {
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: radius,
    backgroundColor: colors.surface,
    overflow: 'hidden',
  },
  row: {
    minHeight: touchHeight,
    justifyContent: 'center',
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.xs,
  },
  rowDivider: {
    borderTopWidth: StyleSheet.hairlineWidth,
    borderTopColor: colors.border,
  },
  rowPressed: {
    backgroundColor: colors.selected,
  },
  plate: {
    fontSize: fontSize.body,
    fontFamily: fonts.bold,
    letterSpacing: 0.3,
    color: colors.text,
  },
  detail: {
    fontSize: fontSize.small,
    fontFamily: fonts.medium,
    color: colors.muted,
  },
});
