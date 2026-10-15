<script setup lang="ts">
import type { CheckboxRootEmits, CheckboxRootProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { Check } from "@lucide/vue"
import { reactiveOmit } from "@vueuse/core"
import { CheckboxIndicator, CheckboxRoot, useForwardPropsEmits } from "reka-ui"
import { onBeforeUnmount, onMounted, ref, useAttrs } from "vue"
import { cn } from "@/lib/utils"

const props = defineProps<CheckboxRootProps & { class?: HTMLAttributes["class"] }>()
const emits = defineEmits<CheckboxRootEmits>()

const delegatedProps = reactiveOmit(props, "class")

const forwarded = useForwardPropsEmits(delegatedProps, emits)

// Reka reads the <label for> text once, at mount, and sets it as aria-label.
// Translations load after that and the locale can change without a reload, so
// keep the name in step with the label instead of announcing a stale string.
const attrs = useAttrs()
const labelText = ref<string>()
let observer: MutationObserver | undefined

onMounted(() => {
  if (!props.id || attrs["aria-label"] || attrs["aria-labelledby"]) return
  const label = document.querySelector<HTMLElement>(`label[for="${CSS.escape(props.id)}"]`)
  if (!label) return
  const read = () => {
    labelText.value = label.innerText.trim() || undefined
  }
  read()
  observer = new MutationObserver(read)
  observer.observe(label, { childList: true, characterData: true, subtree: true })
})

onBeforeUnmount(() => observer?.disconnect())
</script>

<template>
  <CheckboxRoot
    v-slot="slotProps"
    data-slot="checkbox"
    v-bind="forwarded"
    :aria-label="labelText"
    :class="
      cn('peer border-input data-[state=checked]:bg-primary data-[state=checked]:text-primary-foreground data-[state=checked]:border-primary focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive size-4 shrink-0 rounded-[4px] border shadow-xs transition-shadow outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50',
         props.class)"
  >
    <CheckboxIndicator
      data-slot="checkbox-indicator"
      class="grid place-content-center text-current transition-none"
    >
      <slot v-bind="slotProps">
        <Check class="size-3.5" />
      </slot>
    </CheckboxIndicator>
  </CheckboxRoot>
</template>
