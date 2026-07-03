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
