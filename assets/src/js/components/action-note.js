/**
 * mc-action-note - a small bubble next to the control that was just used
 * ("Added to wishlist" + a link to the list). Not a modal and not a corner
 * toast: it is anchored to the button, closes by itself, on Escape, on a
 * click elsewhere or when the page scrolls.
 */
const SHOW_MS = 4000;

let current = null;

function close() {
    if (!current) {
        return;
    }

    clearTimeout(current.timer);
    current.note.remove();
    document.removeEventListener('keydown', current.onKey);
    document.removeEventListener('pointerdown', current.onPointer, true);
    window.removeEventListener('scroll', close);
    current = null;
}

export function showActionNote(anchor, { message, linkText = '', linkUrl = '' }) {
    close();

    if (!message) {
        return;
    }

    const note = document.createElement('div');
    note.className = 'mc-action-note';
    note.setAttribute('role', 'status');

    const text = document.createElement('span');
    text.className = 'mc-action-note__text';
    text.textContent = message;
    note.append(text);

    if (linkUrl && linkText) {
        const link = document.createElement('a');
        link.className = 'mc-action-note__link';
        link.href = linkUrl;
        link.textContent = linkText;
        note.append(link);
    }

    document.body.append(note);

    // Under the button, right edges aligned, kept inside the viewport; above it when there is no room below.
    const rect = anchor.getBoundingClientRect();
    const width = note.offsetWidth;
    const height = note.offsetHeight;
    const gap = 10;
    const margin = 8;
    const viewportWidth = document.documentElement.clientWidth;
    const left = Math.min(Math.max(rect.right - width, margin), viewportWidth - width - margin);
    const below = rect.bottom + gap + height + margin <= window.innerHeight || rect.top - gap - height < margin;
    const top = below ? rect.bottom + gap : rect.top - gap - height;

    note.style.left = `${left + window.scrollX}px`;
    note.style.top = `${top + window.scrollY}px`;
    note.style.setProperty('--mc-action-note-arrow', `${Math.min(Math.max(rect.left + rect.width / 2 - left, 16), width - 16)}px`);
    note.classList.add(below ? 'is-below' : 'is-above');

    const onKey = (event) => {
        if (event.key === 'Escape') {
            close();
        }
    };
    const onPointer = (event) => {
        if (!note.contains(event.target)) {
            close();
        }
    };

    document.addEventListener('keydown', onKey);
    document.addEventListener('pointerdown', onPointer, true);
    window.addEventListener('scroll', close, { passive: true });

    current = { note, onKey, onPointer, timer: setTimeout(close, SHOW_MS) };
    requestAnimationFrame(() => note.classList.add('is-visible'));
}
