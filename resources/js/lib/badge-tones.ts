/**
 * The colors a status tag comes in, one per meaning, so the same state looks
 * the same in every list and dialog. Each tone is a see-through fill of a mid
 * shade: vivid enough to read on a white card, a soft glow on a dark one.
 */
export const badgeTones = {
    success:
        'border-emerald-600/25 bg-emerald-500/15 text-emerald-700 dark:border-emerald-400/25 dark:bg-emerald-400/10 dark:text-emerald-300',
    info: 'border-sky-600/25 bg-sky-500/15 text-sky-800 dark:border-sky-400/25 dark:bg-sky-400/10 dark:text-sky-300',
    warning:
        'border-amber-600/30 bg-amber-400/25 text-amber-800 dark:border-amber-400/25 dark:bg-amber-400/10 dark:text-amber-300',
    danger: 'border-rose-600/25 bg-rose-500/15 text-rose-700 dark:border-rose-400/25 dark:bg-rose-400/10 dark:text-rose-300',
    accent: 'border-violet-600/25 bg-violet-500/15 text-violet-700 dark:border-violet-400/25 dark:bg-violet-400/10 dark:text-violet-300',
    neutral:
        'border-gray-500/25 bg-gray-500/10 text-gray-600 dark:border-gray-400/20 dark:bg-gray-400/10 dark:text-gray-300',
} as const;

export type BadgeTone = keyof typeof badgeTones;

/** The solid dot that marks a status in its tone, inside a tag or a select option. */
export const badgeDotTones: Record<BadgeTone, string> = {
    success: 'bg-emerald-500',
    info: 'bg-sky-500',
    warning: 'bg-amber-500',
    danger: 'bg-rose-500',
    accent: 'bg-violet-500',
    neutral: 'bg-gray-400',
};

/** The tile behind an icon, such as on a summary card or an agenda item, in the tags' tones. */
export const iconTones: Record<BadgeTone, string> = {
    success:
        'bg-emerald-500/15 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-300',
    info: 'bg-sky-500/15 text-sky-800 dark:bg-sky-400/10 dark:text-sky-300',
    warning:
        'bg-amber-400/25 text-amber-800 dark:bg-amber-400/10 dark:text-amber-300',
    danger: 'bg-rose-500/15 text-rose-700 dark:bg-rose-400/10 dark:text-rose-300',
    accent: 'bg-violet-500/15 text-violet-700 dark:bg-violet-400/10 dark:text-violet-300',
    neutral:
        'bg-gray-500/10 text-gray-600 dark:bg-gray-400/10 dark:text-gray-300',
};
