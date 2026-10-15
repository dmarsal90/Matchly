import { describe, expect, it } from 'vitest';
import { diffSequence, diffWords, hasChanges } from './diff';

describe('diffSequence', () => {
    it('keeps common items and marks additions and removals in order', () => {
        expect(diffSequence(['a', 'b', 'c'], ['a', 'c', 'd'])).toEqual([
            { type: 'same', value: 'a' },
            { type: 'removed', value: 'b' },
            { type: 'same', value: 'c' },
            { type: 'added', value: 'd' },
        ]);
    });

    it('shows a reorder as a removal and an addition', () => {
        const parts = diffSequence(['php', 'vue'], ['vue', 'php']);

        expect(parts.filter((part) => part.type === 'same')).toHaveLength(1);
        expect(hasChanges(parts)).toBe(true);
    });

    it('handles empty sequences', () => {
        expect(diffSequence([], ['x'])).toEqual([
            { type: 'added', value: 'x' },
        ]);
        expect(diffSequence(['x'], [])).toEqual([
            { type: 'removed', value: 'x' },
        ]);
        expect(hasChanges(diffSequence(['x'], ['x']))).toBe(false);
    });
});

describe('diffWords', () => {
    it('diffs texts word by word', () => {
        expect(
            diffWords(
                'Built APIs with Laravel',
                'Built REST APIs with Laravel',
            ),
        ).toEqual([
            { type: 'same', value: 'Built ' },
            { type: 'added', value: 'REST ' },
            { type: 'same', value: 'APIs ' },
            { type: 'same', value: 'with ' },
            { type: 'same', value: 'Laravel' },
        ]);
    });
});
