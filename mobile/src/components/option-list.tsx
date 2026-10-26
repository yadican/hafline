import { useState } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';

import { colors, fontSize, fonts, radius, spacing, touchHeight } from '@/theme';

import { TextField } from './text-field';

export type ListOption = {
  value: string;
  label: string;
};

type OptionListProps = {
  options: ListOption[];
  selected: string | null;
  onSelect: (value: string) => void;
  emptyText: string;
  searchThreshold?: number;
};

export function OptionList({ options, selected, onSelect, emptyText, searchThreshold = 8 }: OptionListProps) {
  const [query, setQuery] = useState('');
  const searchable = options.length > searchThreshold;
  const needle = query.trim().toLocaleLowerCase('tr');
  const visible = needle
    ? options.filter((option) => option.label.toLocaleLowerCase('tr').includes(needle))
    : options;

  if (options.length === 0) {
    return <Text style={styles.empty}>{emptyText}</Text>;
  }

  return (
    <View style={styles.container}>
      {searchable ? (
        <TextField label="Ara" value={query} onChangeText={setQuery} placeholder="Listede ara" />
      ) : null}
      <View style={styles.list}>
        {visible.map((option, index) => {
          const isSelected = option.value === selected;

          return (
            <Pressable
              key={option.value}
              accessibilityRole="radio"
              accessibilityState={{ selected: isSelected }}
              onPress={() => onSelect(option.value)}
              style={({ pressed }) => [
                styles.row,
                index > 0 && styles.rowDivider,
                isSelected && styles.rowSelected,
                pressed && styles.rowPressed,
              ]}
            >
              <View style={[styles.radio, isSelected && styles.radioSelected]}>
                {isSelected ? <View style={styles.radioDot} /> : null}
              </View>
              <Text style={[styles.label, isSelected && styles.labelSelected]}>{option.label}</Text>
            </Pressable>
          );
        })}
        {visible.length === 0 ? <Text style={styles.empty}>Sonuç yok</Text> : null}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    gap: spacing.sm,
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
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.md,
    paddingHorizontal: spacing.md,
  },
  rowDivider: {
    borderTopWidth: StyleSheet.hairlineWidth,
    borderTopColor: colors.border,
  },
  rowSelected: {
    backgroundColor: colors.selected,
  },
  rowPressed: {
    backgroundColor: colors.surfaceMuted,
  },
  radio: {
    width: 22,
    height: 22,
    borderRadius: 11,
    borderWidth: 1.5,
    borderColor: colors.radioBorder,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.surface,
  },
  radioSelected: {
    borderColor: colors.primary,
  },
  radioDot: {
    width: 10,
    height: 10,
    borderRadius: 5,
    backgroundColor: colors.primary,
  },
  label: {
    flex: 1,
    fontSize: fontSize.body,
    fontFamily: fonts.medium,
    color: colors.text,
  },
  labelSelected: {
    fontFamily: fonts.bold,
    color: colors.primaryDark,
  },
  empty: {
    padding: spacing.md,
    fontSize: fontSize.body,
    fontFamily: fonts.medium,
    color: colors.muted,
  },
});
