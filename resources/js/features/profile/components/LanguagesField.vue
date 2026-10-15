<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import type { LanguageSkill } from '@/features/profile/types';
import { Plus, Trash2 } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

defineProps<{
    levels: string[];
    errors: Record<string, string | undefined>;
}>();

const model = defineModel<LanguageSkill[]>({ required: true });

// The 40 most spoken languages in tech hiring; names come from the browser's Intl.
const CODES = [
    'en',
    'es',
    'pt',
    'fr',
    'de',
    'it',
    'nl',
    'pl',
    'ro',
    'uk',
    'ru',
    'tr',
    'ar',
    'he',
    'hi',
    'bn',
    'ur',
    'zh',
    'ja',
    'ko',
    'vi',
    'th',
    'id',
    'ms',
    'fil',
    'sv',
    'no',
    'da',
    'fi',
    'cs',
    'sk',
    'hu',
    'el',
    'bg',
    'hr',
    'sr',
    'ca',
    'eu',
    'gl',
    'sw',
];

const displayNames = computed(
    () =>
        new Intl.DisplayNames([document.documentElement.lang || 'en'], {
            type: 'language',
        }),
);
const options = computed(() =>
    CODES.map((code) => ({
        code,
        name: displayNames.value.of(code) ?? code,
    })).sort((a, b) => a.name.localeCompare(b.name)),
);

function add(): void {
    const used = new Set(model.value.map((language) => language.code));
    const next = options.value.find((option) => !used.has(option.code));
    model.value = [...model.value, { code: next?.code ?? 'en', level: 'B2' }];
}

function remove(index: number): void {
    model.value = model.value.filter((_, i) => i !== index);
}
</script>

<template>
    <div class="grid gap-3">
        <p v-if="model.length === 0" class="text-sm text-muted-foreground">
            {{ trans('profile.fields.no_languages') }}
        </p>

        <div
            v-for="(language, index) in model"
            :key="index"
            class="grid grid-cols-[1fr_1fr_auto] items-end gap-2"
        >
            <div class="grid gap-1.5">
                <Label
                    :for="`language-${index}`"
                    :class="index > 0 && 'sr-only'"
                    >{{ trans('profile.fields.language') }}</Label
                >
                <NativeSelect :id="`language-${index}`" v-model="language.code">
                    <option
                        v-for="option in options"
                        :key="option.code"
                        :value="option.code"
                    >
                        {{ option.name }}
                    </option>
                </NativeSelect>
                <InputError :message="errors[`languages.${index}.code`]" />
            </div>
            <div class="grid gap-1.5">
                <Label
                    :for="`level-${index}`"
                    :class="index > 0 && 'sr-only'"
                    >{{ trans('profile.fields.level') }}</Label
                >
                <NativeSelect :id="`level-${index}`" v-model="language.level">
                    <option v-for="level in levels" :key="level" :value="level">
                        {{ trans(`profile.levels.${level}`) }}
                    </option>
                </NativeSelect>
                <InputError :message="errors[`languages.${index}.level`]" />
            </div>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                :aria-label="trans('profile.fields.remove')"
                @click="remove(index)"
            >
                <Trash2 class="size-4" aria-hidden="true" />
            </Button>
        </div>

        <div>
            <Button type="button" variant="outline" size="sm" @click="add">
                <Plus class="size-4" aria-hidden="true" />
                {{ trans('profile.fields.add_language') }}
            </Button>
        </div>
    </div>
</template>
