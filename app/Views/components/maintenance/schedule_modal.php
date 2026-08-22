<div class="modal fade" id="dynamicModal" tabindex="-1" aria-labelledby="schedule-title" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content" id="schedule-modal-content">
            <?= view('components/maintenance/schedule_content', compact('schedule', 'roomAssets', 'users', 'feedbackType', 'feedbackMessage')) ?>
        </div>
    </div>
</div>
