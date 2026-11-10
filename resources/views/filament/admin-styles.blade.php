<style>
    :root {
        --matchly-accent: #f47761;
        --matchly-accent-soft: #ffe8e2;
        --matchly-bg: #f4f6f4;
        --matchly-surface: #ffffff;
        --matchly-border: rgb(36 51 45 / 9%);
        --matchly-shadow: 0 8px 24px -14px rgb(36 51 45 / 18%), 0 2px 6px rgb(36 51 45 / 4%);
        --matchly-shadow-hover: 0 16px 32px -16px rgb(36 51 45 / 24%), 0 4px 10px rgb(36 51 45 / 5%);
        --matchly-radius: 14px;
        --matchly-ease: cubic-bezier(.22, 1, .36, 1);
    }
    .dark {
        --matchly-bg: #111a17;
        --matchly-surface: rgb(31 45 38 / 78%);
        --matchly-border: rgb(220 237 226 / 12%);
        --matchly-shadow: inset 1px 1px 0 rgb(255 255 255 / 5%), 0 14px 30px -16px rgb(0 0 0 / 65%);
        --matchly-shadow-hover: inset 1px 1px 0 rgb(255 255 255 / 8%), 0 20px 36px -16px rgb(0 0 0 / 78%);
        --matchly-accent-soft: rgb(244 119 97 / 15%);
    }

    .fi-body {
        background:
            radial-gradient(850px 430px at 100% -12%, rgb(244 119 97 / 8%), transparent 62%),
            var(--matchly-bg);
        font-feature-settings: 'cv11', 'ss01';
        -webkit-font-smoothing: antialiased;
    }
    .dark .fi-body {
        background:
            radial-gradient(560px 420px at 3% 24%, rgb(255 255 255 / 7%), transparent 72%),
            radial-gradient(700px 460px at 98% 2%, rgb(244 119 97 / 8%), transparent 70%),
            #111a17;
        background-attachment: fixed;
    }
    .fi-header-heading, .fi-simple-header-heading {
        font-weight: 700;
        letter-spacing: -.03em;
    }

    .fi-topbar {
        background: color-mix(in srgb, var(--matchly-surface) 82%, transparent);
        border-bottom: 1px solid var(--matchly-border);
        box-shadow: none;
        backdrop-filter: saturate(160%) blur(14px);
        -webkit-backdrop-filter: saturate(160%) blur(14px);
    }
    .fi-logo { font-weight: 800; letter-spacing: -.035em; }

    .fi-sidebar {
        background: color-mix(in srgb, var(--matchly-surface) 94%, transparent);
        border-right: 1px solid var(--matchly-border);
        box-shadow: none;
        backdrop-filter: blur(14px);
        -webkit-backdrop-filter: blur(14px);
    }
    .fi-sidebar-group-label {
        font-size: .68rem;
        font-weight: 650;
        letter-spacing: .09em;
        text-transform: uppercase;
        opacity: .68;
    }
    .fi-sidebar-item-btn {
        position: relative;
        border-radius: 10px;
        transition: background-color .2s var(--matchly-ease), transform .2s var(--matchly-ease);
    }
    .fi-sidebar-item-btn:hover { transform: translateX(2px); }
    .fi-sidebar-item.fi-active > .fi-sidebar-item-btn {
        background: linear-gradient(90deg, rgb(244 119 97 / 16%), rgb(244 119 97 / 4%));
        font-weight: 650;
    }
    .fi-sidebar-item.fi-active > .fi-sidebar-item-btn::before {
        position: absolute;
        top: 20%;
        bottom: 20%;
        left: -12px;
        width: 3px;
        border-radius: 0 3px 3px 0;
        background: linear-gradient(180deg, #f47761, #f3a18f);
        content: '';
    }

    .fi-section, .fi-ta-ctn, .fi-wi-stats-overview-stat, .fi-modal-window {
        border: 1px solid var(--matchly-border);
        border-radius: var(--matchly-radius);
        background: var(--matchly-surface);
        box-shadow: var(--matchly-shadow);
    }
    .fi-wi-stats-overview-stat {
        overflow: hidden;
        transition: transform .25s var(--matchly-ease), box-shadow .25s var(--matchly-ease);
    }
    .fi-wi-stats-overview-stat:hover {
        transform: translateY(-2px);
        box-shadow: var(--matchly-shadow-hover);
    }
    .fi-wi-stats-overview-stat-value { font-weight: 700; letter-spacing: -.035em; font-variant-numeric: tabular-nums; }
    .fi-wi-stats-overview-stat-label { font-size: .8rem; font-weight: 550; }

    .fi-ta-header-cell {
        background: color-mix(in srgb, var(--matchly-bg) 72%, var(--matchly-surface));
        font-size: .7rem;
        font-weight: 650;
        letter-spacing: .055em;
        text-transform: uppercase;
    }
    .fi-ta-row { transition: background-color .15s ease; }
    .fi-ta-row:hover { background: rgb(244 119 97 / 4%); }
    .fi-ta-ctn, .fi-ta-content-ctn { max-width: 100%; }
    .fi-ta-content-ctn { overflow-x: auto; }
    .fi-ta-actions { flex-wrap: nowrap; gap: .35rem; }

    .fi-input-wrp { border-radius: 10px; transition: box-shadow .2s ease; }
    .fi-btn {
        border-radius: 10px;
        font-weight: 600;
        transition: transform .15s var(--matchly-ease), box-shadow .2s ease, filter .2s ease;
    }
    .fi-btn:active { transform: scale(.97); }
    .fi-btn.fi-color-primary:not(.fi-outlined):not(.fi-btn-color-gray) {
        background: linear-gradient(135deg, #f47761, #f28c76);
        color: #292d32;
        box-shadow: 0 6px 16px -7px rgb(224 91 67 / 60%);
    }
    .fi-btn.fi-color-primary:not(.fi-outlined):hover {
        filter: brightness(1.04);
        box-shadow: 0 10px 22px -8px rgb(224 91 67 / 58%);
    }
    .fi-badge { border-radius: 999px; font-weight: 600; }
    .fi-breadcrumbs-item-label { font-size: .8rem; }

    .fi-simple-layout {
        background:
            radial-gradient(700px 500px at 12% 8%, rgb(244 119 97 / 17%), transparent 62%),
            radial-gradient(600px 500px at 90% 92%, rgb(41 45 50 / 9%), transparent 62%),
            var(--matchly-bg);
    }
    .fi-simple-main {
        border: 1px solid var(--matchly-border);
        border-radius: 22px;
        background: color-mix(in srgb, var(--matchly-surface) 90%, transparent);
        box-shadow: 0 30px 60px -28px rgb(36 51 45 / 24%);
        backdrop-filter: blur(18px);
    }
    .dark .fi-simple-main { background: var(--matchly-surface); box-shadow: var(--matchly-shadow); }

    .dark .fi-topbar, .dark .fi-sidebar {
        background: rgb(255 255 255 / 5%);
        border-color: var(--matchly-border);
        backdrop-filter: blur(22px) saturate(145%);
        -webkit-backdrop-filter: blur(22px) saturate(145%);
    }
    .dark .fi-section, .dark .fi-ta-ctn, .dark .fi-wi-stats-overview-stat, .dark .fi-modal-window {
        background: linear-gradient(150deg, rgb(255 255 255 / 8%), rgb(255 255 255 / 4%));
        border-color: var(--matchly-border);
        backdrop-filter: blur(18px) saturate(135%);
        -webkit-backdrop-filter: blur(18px) saturate(135%);
    }
    .dark .fi-ta-header-cell { background: rgb(255 255 255 / 4%); }
    .dark .fi-ta-row:hover { background: rgb(244 119 97 / 7%); }
    .dark .fi-simple-layout {
        background:
            radial-gradient(700px 500px at 12% 8%, rgb(244 119 97 / 12%), transparent 62%),
            radial-gradient(600px 500px at 90% 92%, rgb(255 255 255 / 5%), transparent 62%),
            #111a17;
    }

    input[type='checkbox'], input[type='radio'] { accent-color: var(--matchly-accent); }
    .fi-checkbox-input:checked, .fi-toggle-on { background-color: var(--matchly-accent); border-color: var(--matchly-accent); }
    .fi-toggle-on { color: #292d32; }
    .fi-ta-actions .fi-btn { min-width: 2.4rem; min-height: 2.4rem; justify-content: center; }
    .fi-ta-actions .fi-btn:focus-visible { outline: 2px solid var(--matchly-accent); outline-offset: 2px; }

    * { scrollbar-width: thin; scrollbar-color: rgb(102 115 108 / 35%) transparent; }
    @media (prefers-reduced-transparency: reduce) {
        .fi-topbar, .fi-sidebar, .fi-section, .fi-ta-ctn, .fi-wi-stats-overview-stat, .fi-modal-window, .fi-simple-main { backdrop-filter: none; }
    }
    @media (prefers-reduced-motion: reduce) {
        .fi-sidebar-item-btn, .fi-wi-stats-overview-stat, .fi-btn, .fi-ta-row { transition: none; }
        .fi-sidebar-item-btn:hover, .fi-wi-stats-overview-stat:hover { transform: none; }
    }
</style>
