export type DiffPart<T> = { type: 'same' | 'added' | 'removed'; value: T };

/**
 * Longest-common-subsequence diff of two sequences: what was kept, added
 * and removed, in reading order. Used to show how the tailored CV differs
 * from the person's own (bullets, skills, words of the summary).
 */
export function diffSequence<T>(before: T[], after: T[]): DiffPart<T>[] {
    const rows = before.length;
    const cols = after.length;
    const lengths: number[][] = Array.from({ length: rows + 1 }, () =>
        new Array<number>(cols + 1).fill(0),
    );

    for (let i = rows - 1; i >= 0; i--) {
        for (let j = cols - 1; j >= 0; j--) {
            lengths[i][j] =
                before[i] === after[j]
                    ? lengths[i + 1][j + 1] + 1
                    : Math.max(lengths[i + 1][j], lengths[i][j + 1]);
        }
    }

    const parts: DiffPart<T>[] = [];
    let i = 0;
    let j = 0;
    while (i < rows && j < cols) {
        if (before[i] === after[j]) {
            parts.push({ type: 'same', value: before[i] });
            i++;
            j++;
        } else if (lengths[i + 1][j] >= lengths[i][j + 1]) {
            parts.push({ type: 'removed', value: before[i++] });
        } else {
            parts.push({ type: 'added', value: after[j++] });
        }
    }
    while (i < rows) parts.push({ type: 'removed', value: before[i++] });
    while (j < cols) parts.push({ type: 'added', value: after[j++] });

    return parts;
}

/** Word-level diff of two texts (whitespace is kept with each word). */
export function diffWords(before: string, after: string): DiffPart<string>[] {
    const split = (text: string): string[] => text.match(/\S+\s*/g) ?? [];

    return diffSequence(split(before), split(after));
}

export function hasChanges<T>(parts: DiffPart<T>[]): boolean {
    return parts.some((part) => part.type !== 'same');
}
