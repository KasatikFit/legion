    <!-- Модальное окно спортсмена (страница тренера) -->
    <div class="modal-overlay" id="athleteModal" onclick="closeModal(event)">
        <div class="modal" onclick="event.stopPropagation()">
            <button class="modal-close" onclick="closeModal()">✖</button>
            <div class="modal-header">
                <div id="modal-photo-frame" class="photo-frame league-none">
                    <img id="modal-photo" class="athlete-photo" src="" alt="Фото" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22120%22 height=%22120%22%3E%3Ccircle cx=%2260%22 cy=%2260%22 r=%2250%22 fill=%22%23222%22/%3E%3Ctext x=%2260%22 y=%2270%22 text-anchor=%22middle%22 font-size=%2240%22 fill=%22%23888%22%3E👤%3C/text%3E%3C/svg%3E'">
                </div>
                <h2 id="modal-name"></h2>
                <p id="modal-league"></p>
            </div>
            <p>
                <strong>Место у тренера:</strong> <span id="modal-rank-coach"></span> |
                <strong>Общее место:</strong> <span id="modal-rank-overall"></span>
            </p>
            <div class="table-wrap">
                <table class="rating-table">
                    <thead><tr><th>Упражнение</th><th>Результат</th><th>Место</th><th>Очки</th></tr></thead>
                    <tbody id="modal-body"></tbody>
                </table>
            </div>
            <div id="modal-rank-info" class="modal-rank-info"></div>
            <div id="modal-achievements" class="achievements"></div>
            <div id="modal-history" class="modal-history-block"></div>
        </div>
    </div>

    <div class="modal-overlay" id="rankModal" onclick="closeRankModal(event)">
        <div class="modal modal--rank" onclick="event.stopPropagation()">
            <button class="modal-close" onclick="closeRankModal()">✖</button>
            <h2 id="rank-modal-title" class="rank-modal-title"></h2>
            <div id="rank-modal-content" class="rank-modal-body"></div>
        </div>
    </div>

    <div class="modal-overlay" id="infoModal" onclick="closeInfoModal(event)">
        <div class="modal" onclick="event.stopPropagation()">
            <button class="modal-close" onclick="closeInfoModal()">✖</button>
            <h2>О системе рейтинга</h2>
            <p><strong>Как начисляются баллы?</strong></p>
            <ul>
                <li>В каждом упражнении спортсмены получают очки за занятое место.</li>
                <li>1‑е место = 100 баллов, 2‑е = 95, 3‑е = 90, 4‑е = 88, … и так далее с шагом −2 до 0.</li>
                <li>При одинаковых результатах место делится, и все получают одинаковые очки.</li>
            </ul>
            <p><strong>Рейтинг у тренера</strong></p>
            <ul>
                <li>Очки считаются только среди воспитанников данного тренера.</li>
                <li>Это позволяет увидеть, кто лучший в своей команде.</li>
            </ul>
            <p><strong>Общий рейтинг (Легион Силы)</strong></p>
            <ul>
                <li>Все спортсмены клуба участвуют в общем зачёте. Баллы начисляются по тому же принципу, но уже среди всех.</li>
                <li>Лучшие 25 формируют <strong>ТОП‑25 Легиона Силы</strong> – элиту клуба.</li>
                <li>Остальные отображаются в списке <strong>«Легионеры»</strong>.</li>
            </ul>
            <p><strong>При равенстве суммы очков</strong> сравниваются результаты по порядку: подтягивания → отжимания → вис → бёрпи → скручивания → прыжок.</p>
            <p>Данные обновляются автоматически каждую минуту из Google Таблиц тренеров.</p>
        </div>
    </div>
