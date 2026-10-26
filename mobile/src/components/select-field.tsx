import { useState } from 'react';
import { FlatList, Modal, Pressable, StyleSheet, Text, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { colors, fontSize, fonts, radius, spacing, touchHeight } from '@/theme';

import { TextField } from './text-field';

export type ListOption = {
  value: string;
  label: string;
};

type SelectFieldProps = {
  options: ListOption[];
  selected: string | null;
  onSelect: (value: string) => void;
  emptyText: string;
  title: string;
  placeholder?: string;
  searchThreshold?: number;
};

export function SelectField({
  options,
  selected,
  onSelect,
  emptyText,
  title,
  placeholder = 'Seçin',
  searchThreshold = 5,
}: SelectFieldProps) {
  const insets = useSafeAreaInsets();
  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState('');

  const searchable = options.length > searchThreshold;
  const needle = query.trim().toLocaleLowerCase('tr');
  const visible = needle
    ? options.filter((option) => option.label.toLocaleLowerCase('tr').includes(needle))
    : options;
  const current = options.find((option) => option.value === selected);

  if (options.length === 0) {
    return <Text style={styles.empty}>{emptyText}</Text>;
  }

  const close = () => {
    setOpen(false);
    setQuery('');
  };

  const choose = (value: string) => {
    onSelect(value);
    close();
  };

  return (
    <>
      <Pressable
        accessibilityRole="button"
        accessibilityLabel={title}
        accessibilityValue={{ text: current?.label ?? placeholder }}
        onPress={() => setOpen(true)}
        style={({ pressed }) => [styles.trigger, pressed && styles.triggerPressed]}
      >
        <Text numberOfLines={1} style={[styles.triggerText, !current && styles.placeholder]}>
          {current?.label ?? placeholder}
        </Text>
        <View style={styles.chevron} />
      </Pressable>

      <Modal visible={open} transparent animationType="slide" statusBarTranslucent onRequestClose={close}>
        <View style={styles.backdrop}>
          <Pressable style={StyleSheet.absoluteFill} onPress={close} accessibilityLabel="Kapat" />
          <View style={[styles.sheet, { paddingBottom: insets.bottom + spacing.md }]}>
            <View style={styles.header}>
              <Text style={styles.title}>{title}</Text>
              <Pressable accessibilityRole="button" onPress={close} hitSlop={12} style={styles.closeButton}>
                <Text style={styles.closeText}>Kapat</Text>
              </Pressable>
            </View>

            {searchable ? (
              <TextField label="Ara" value={query} onChangeText={setQuery} placeholder="Listede ara" />
            ) : null}

            <FlatList
              data={visible}
              keyExtractor={(option) => option.value}
              keyboardShouldPersistTaps="handled"
              style={styles.list}
              ItemSeparatorComponent={Divider}
              ListEmptyComponent={<Text style={styles.empty}>Sonuç yok</Text>}
              renderItem={({ item }) => {
                const isSelected = item.value === selected;

                return (
                  <Pressable
                    accessibilityRole="radio"
                    accessibilityState={{ selected: isSelected }}
                    onPress={() => choose(item.value)}
                    style={({ pressed }) => [
                      styles.row,
                      isSelected && styles.rowSelected,
                      pressed && styles.rowPressed,
                    ]}
                  >
                    <View style={[styles.radio, isSelected && styles.radioSelected]}>
                      {isSelected ? <View style={styles.radioDot} /> : null}
                    </View>
                    <Text style={[styles.label, isSelected && styles.labelSelected]}>{item.label}</Text>
                  </Pressable>
                );
              }}
            />
          </View>
        </View>
      </Modal>
    </>
  );
}

function Divider() {
  return <View style={styles.divider} />;
}

const styles = StyleSheet.create({
  trigger: {
    minHeight: touchHeight,
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.md,
    borderWidth: 1,
    borderColor: colors.inputBorder,
    borderRadius: radius,
    backgroundColor: colors.surface,
    paddingHorizontal: spacing.md,
  },
  triggerPressed: {
    backgroundColor: colors.surfaceMuted,
  },
  triggerText: {
    flex: 1,
    fontSize: fontSize.body,
    fontFamily: fonts.semibold,
    color: colors.text,
  },
  placeholder: {
    fontFamily: fonts.medium,
    color: colors.placeholder,
  },
  chevron: {
    width: 10,
    height: 10,
    marginTop: -4,
    borderRightWidth: 2,
    borderBottomWidth: 2,
    borderColor: colors.muted,
    transform: [{ rotate: '45deg' }],
  },
  backdrop: {
    flex: 1,
    justifyContent: 'flex-end',
    backgroundColor: 'rgba(14, 17, 20, 0.45)',
  },
  sheet: {
    maxHeight: '75%',
    gap: spacing.sm,
    paddingTop: spacing.md,
    paddingHorizontal: spacing.md,
    borderTopLeftRadius: radius * 2,
    borderTopRightRadius: radius * 2,
    backgroundColor: colors.background,
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  title: {
    flex: 1,
    fontSize: fontSize.body,
    fontFamily: fonts.extrabold,
    color: colors.text,
  },
  closeButton: {
    paddingVertical: spacing.xs,
    paddingHorizontal: spacing.sm,
  },
  closeText: {
    fontSize: fontSize.small,
    fontFamily: fonts.bold,
    color: colors.primaryDark,
  },
  list: {
    flexGrow: 0,
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: radius,
    backgroundColor: colors.surface,
  },
  row: {
    minHeight: touchHeight,
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.md,
    paddingHorizontal: spacing.md,
  },
  divider: {
    height: StyleSheet.hairlineWidth,
    backgroundColor: colors.border,
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
