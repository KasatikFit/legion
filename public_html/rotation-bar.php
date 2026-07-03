    <div class="rotation-panel no-print" id="rotation-panel">
        <div class="rotation-panel-card">
            <div class="rotation-panel-accent" aria-hidden="true"></div>
            <div class="rotation-panel-content">
                <div class="rotation-panel-icon" aria-hidden="true">
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 4V2M12 22V20M4 12H2M22 12H20M5.64 5.64L4.22 4.22M19.78 19.78L18.36 18.36M5.64 18.36L4.22 19.78M19.78 4.22L18.36 5.64" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>
                        <path d="M12 16C14.2091 16 16 14.2091 16 12C16 9.79086 14.2091 8 12 8C9.79086 8 8 9.79086 8 12C8 14.2091 9.79086 16 12 16Z" stroke="currentColor" stroke-width="1.75"/>
                        <path d="M12 6C8.68629 6 6 8.68629 6 12" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>
                    </svg>
                </div>
                <div class="rotation-panel-text">
                    <h3 class="rotation-panel-title"><?php echo htmlspecialchars($rotationTitle ?? 'Ротация лиг'); ?></h3>
                    <p class="rotation-panel-hint"><?php echo htmlspecialchars($rotationHint ?? 'Элита обновляется автоматически в начале месяца. Здесь можно провести ротацию вручную — потребуется пароль.'); ?></p>
                </div>
                <button type="button" class="rotation-btn" id="rotation-btn" onclick="rotateLeagues()">
                    <span class="rotation-btn-spinner" aria-hidden="true"></span>
                    <span class="rotation-btn-icon" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M20 12C20 16.4183 16.4183 20 12 20C8.31447 20 5.21994 17.2091 4.27146 13.75M4 12C4 7.58172 7.58172 4 12 4C15.6855 4 18.7801 6.79086 19.7285 10.25" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            <path d="M19 4V10H13M5 20V14H11" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>
                    <span class="rotation-btn-label">Провести ротацию</span>
                </button>
            </div>
        </div>
        <div id="rotation-log" class="rotation-log" hidden></div>
    </div>
