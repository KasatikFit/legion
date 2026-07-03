    <div class="search-bar no-print" role="search">
        <div class="search-bar-inner">
            <span class="search-icon" aria-hidden="true">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2"/>
                    <path d="M20 20L16.5 16.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </span>
            <input
                type="search"
                id="athlete-search"
                class="search-input"
                placeholder="Найти спортсмена по фамилии или имени…"
                autocomplete="off"
                enterkeyhint="search"
                aria-describedby="athlete-search-status"
            >
            <button type="button" class="search-clear" id="athlete-search-clear" aria-label="Очистить поиск" hidden>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M6 6L18 18M18 6L6 18" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
                </svg>
            </button>
        </div>
        <p class="search-status" id="athlete-search-status" aria-live="polite"></p>
    </div>
