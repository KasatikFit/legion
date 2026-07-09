<?php
/** Модалки пилотной группы (рейтинг + ранги). */
?>
<div class="modal-overlay" id="athleteModal" onclick="LegionPilotRating.closeModal(event)">
    <div class="modal" onclick="event.stopPropagation()">
        <button type="button" class="modal-close" onclick="LegionPilotRating.closeModal()">✖</button>
        <div class="modal-header">
            <div id="modal-photo-frame" class="photo-frame league-none">
                <img id="modal-photo" class="athlete-photo" src="" alt="Фото">
            </div>
            <h2 id="modal-name"></h2>
            <p id="modal-league"></p>
        </div>
        <p id="modal-ranks-row" hidden>
            <strong>Место в группе:</strong> <span id="modal-rank-coach"></span>
        </p>
        <div class="table-wrap">
            <table class="rating-table">
                <thead><tr><th>Упражнение</th><th>Результат</th><th>Место</th><th>Очки</th></tr></thead>
                <tbody id="modal-body"></tbody>
            </table>
        </div>
        <div id="modal-rank-info" class="modal-rank-info"></div>
        <div id="modal-achievements" class="achievements pilot-modal-achievements"></div>
        <div id="modal-progress" class="modal-progress-block"></div>
    </div>
</div>

<div class="modal-overlay" id="rankModal" onclick="LegionPilotRating.closeRankModal(event)">
    <div class="modal modal--rank" onclick="event.stopPropagation()">
        <button type="button" class="modal-close" onclick="LegionPilotRating.closeRankModal()">✖</button>
        <h2 id="rank-modal-title" class="rank-modal-title"></h2>
        <div id="rank-modal-content" class="rank-modal-body"></div>
    </div>
</div>
