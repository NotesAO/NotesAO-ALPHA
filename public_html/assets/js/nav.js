/* NotesAO – dropdown-nav  (assets/js/nav.js)
   Handles: click-to-toggle, hover-to-open (desktop),
            click-outside / Esc-key to close.           */

            document.addEventListener('DOMContentLoaded', () => {

                const DROPDOWNS = document.querySelectorAll('.dropdown');
              
                /* ───────── helper functions ───────── */
                const closeAll = () => {
                  DROPDOWNS.forEach(d => {
                    d.classList.remove('open');
                    const t = d.querySelector('.drop-toggle');
                    if (t) t.setAttribute('aria-expanded', 'false');
                  });
                };
              
                const openDrop = (dd) => {
                  closeAll();
                  dd.classList.add('open');
                  const t = dd.querySelector('.drop-toggle');
                  if (t) t.setAttribute('aria-expanded', 'true');
                };
              
                /* ───────── click-to-toggle (works mobile & desktop) ───────── */
                document.querySelectorAll('.drop-toggle').forEach(btn => {
                  btn.addEventListener('click', e => {
                    e.preventDefault();                       // stop link-style jumps
                    e.stopPropagation();
                    const dd = e.currentTarget.parentElement;
                    dd.classList.contains('open') ? closeAll() : openDrop(dd);
                  });
                });
              
                /* ───────── hover-open / hover-close (only on devices that have hover) ───────── */
                if (window.matchMedia('(hover:hover)').matches) {
                  DROPDOWNS.forEach(dd => {
                    dd.addEventListener('mouseenter', () => openDrop(dd));
                    dd.addEventListener('mouseleave', closeAll);
                  });
                }
              
                /* ───────── close when clicking outside or pressing Esc ───────── */
                document.addEventListener('click', e => {
                  if (!e.target.closest('.dropdown')) closeAll();
                });
              
                document.addEventListener('keydown', e => {
                  if (e.key === 'Escape') closeAll();
                });
              
              });
              