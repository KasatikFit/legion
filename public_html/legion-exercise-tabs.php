<?php
require_once __DIR__ . '/api/exercises_lib.php';

$legionExerciseTabsIncludeHall = !isset($legionExerciseTabsIncludeHall) || $legionExerciseTabsIncludeHall !== false;
$legionExerciseTabActive = isset($legionExerciseTabActive) ? (string) $legionExerciseTabActive : 'overall';
$legionExercises = legion_exercises_config();
?>
<div class="tabs no-print" id="legion-exercise-tabs" data-include-hall="<?php echo $legionExerciseTabsIncludeHall ? '1' : '0'; ?>" data-tabs-rendered="php">
    <div class="<?php echo legion_exercise_tab_class('overall', $legionExerciseTabActive); ?>" data-tab="overall" role="tab" tabindex="0">Общий рейтинг</div>
    <?php foreach ($legionExercises as $ex): ?>
    <div class="<?php echo legion_exercise_tab_class($ex['tab'], $legionExerciseTabActive); ?>" data-tab="<?php echo htmlspecialchars($ex['tab'], ENT_QUOTES, 'UTF-8'); ?>" role="tab" tabindex="0"><?php echo htmlspecialchars($ex['label'], ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endforeach; ?>
    <?php if ($legionExerciseTabsIncludeHall): ?>
    <div class="<?php echo legion_exercise_tab_class('hall', $legionExerciseTabActive); ?>" data-tab="hall" role="tab" tabindex="0">🏆 Зал славы</div>
    <?php endif; ?>
</div>
