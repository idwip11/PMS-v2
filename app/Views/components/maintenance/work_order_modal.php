<div class="modal fade" id="dynamicModal" tabindex="-1" aria-labelledby="work-order-title" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content" id="work-order-modal-content">
            <?= view('components/maintenance/work_order_content', compact('ticket', 'users', 'logs', 'ticketParts', 'spareParts', 'attachments', 'materialCost', 'feedbackType', 'feedbackMessage')) ?>
        </div>
    </div>
</div>
