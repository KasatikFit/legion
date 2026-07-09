/**
 * Фото спортсменов пилотной группы: загруженные и дефолтные аватары.
 */
const LegionPilotPhotos = {
    avatarCount: 10,

    emojiByIndex: ['🏋️', '🏃', '🥊', '🤸', '🏊', '🚴', '🤼', '🏹', '⛷️', '🥋'],

    normalizeName(name) {
        if (typeof LegionCore !== 'undefined' && LegionCore.normalizePersonName) {
            return LegionCore.normalizePersonName(name);
        }
        return String(name || '').replace(/\u00a0/g, ' ').trim().replace(/\s+/g, ' ');
    },

    avatarIndexFor(athleteOrName) {
        if (athleteOrName && typeof athleteOrName === 'object') {
            const idx = parseInt(athleteOrName.avatarIndex, 10);
            if (!Number.isNaN(idx) && idx >= 1 && idx <= this.avatarCount) {
                return idx;
            }
            athleteOrName = athleteOrName.name;
        }
        const norm = this.normalizeName(athleteOrName);
        if (!norm) return 1;
        let hash = 0;
        for (let i = 0; i < norm.length; i++) {
            hash = ((hash << 5) - hash) + norm.charCodeAt(i);
            hash |= 0;
        }
        if (hash < 0) hash = -hash;
        return (hash % this.avatarCount) + 1;
    },

    emojiFor(athleteOrName) {
        const idx = this.avatarIndexFor(athleteOrName);
        return this.emojiByIndex[idx - 1] || '🏅';
    },

    photoSrc(athlete) {
        if (!athlete || typeof athlete !== 'object') {
            return `/api/pilot/default_avatar.php?i=1`;
        }
        if (athlete.hasPhoto && athlete.photo && !/^data:image\//i.test(athlete.photo)) {
            return athlete.photo;
        }
        const idx = this.avatarIndexFor(athlete);
        return `/api/pilot/default_avatar.php?i=${idx}`;
    },

    resolveUrl(athleteOrName, storedPhoto) {
        if (athleteOrName && typeof athleteOrName === 'object') {
            return this.photoSrc(athleteOrName);
        }
        const raw = String(storedPhoto || '').trim();
        if (raw && !/^data:image\//i.test(raw)) {
            if (/^https?:\/\//i.test(raw)) return raw;
            return raw.startsWith('/') ? raw : `/${raw}`;
        }
        const idx = this.avatarIndexFor(athleteOrName);
        return `/api/pilot/default_avatar.php?i=${idx}`;
    },

    isInlinePhoto(url) {
        return /^data:image\//i.test(String(url || ''));
    },

    withCacheBust(url, bust) {
        const raw = String(url || '');
        if (!bust || this.isInlinePhoto(raw)) return raw;
        const sep = raw.includes('?') ? '&' : '?';
        return `${raw}${sep}v=${encodeURIComponent(bust)}`;
    },

    slotHtml(athlete, bust, className, escAttr) {
        const esc = typeof escAttr === 'function' ? escAttr : (v) => String(v || '');
        const cls = className || 'pilot-photo-img';
        const src = this.withCacheBust(this.photoSrc(athlete), bust);
        const emoji = this.emojiFor(athlete);
        const sizeClass = cls.indexOf('row') !== -1 ? 'pilot-avatar-slot--row' : 'pilot-avatar-slot--card';
        return `<span class="pilot-avatar-slot ${sizeClass}">` +
            `<img src="${esc(src)}" alt="" class="${cls}" loading="lazy" decoding="async">` +
            `<span class="pilot-avatar-emoji" aria-hidden="true">${emoji}</span>` +
            `</span>`;
    },

    onImgError(img) {
        if (!img || !img.classList) return;
        img.classList.add('is-broken');
    }
};
