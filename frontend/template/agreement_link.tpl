<div class="axytos-agreement-link ms-4 mt-2 small">
{lang key='agreement_text' section='axytos_payment'}
  <a href="{$axytos_agreement_link}" class="agreement-link">{lang key='agreement_link' section='axytos_payment'}</a>
</div>

<!-- Bootstrap Modal -->
<div class="modal fade" id="agreementModal" tabindex="-1" aria-labelledby="agreementModalLabel" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="agreementModalLabel">{lang key='agreement_title' section='axytos_payment'}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0">
        <iframe id="agreementFrame" src="" class="w-100 border-0" style="height: 70vh;"></iframe>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{lang key='agreement_close' section='axytos_payment'}</button>
<script>
document.addEventListener('DOMContentLoaded', function() {
  // Ensure the modal is in the body element for proper z-index handling
  var modalElement = document.getElementById('agreementModal');
  if (modalElement.parentNode !== document.body) {
    document.body.appendChild(modalElement);
  }

  // Initialize the modal properly to ensure all Bootstrap event handlers are attached
  var modalInstance = new bootstrap.Modal(modalElement);
  
  // Set up event listener for the agreement link
  document.querySelectorAll('.agreement-link').forEach(function(link) {
    link.addEventListener('click', function(event) {
      event.preventDefault();
      var url = this.getAttribute('href');
      document.getElementById('agreementFrame').src = url;
      modalInstance.show();
    });
  });
  
  // Ensure close button works by explicitly adding an event listener
  document.querySelector('.modal .btn-close').addEventListener('click', function() {
    modalInstance.hide();
  });
  
  document.querySelector('.modal .btn-secondary').addEventListener('click', function() {
    modalInstance.hide();
  });
  
  // Clear iframe when modal is hidden
  modalElement.addEventListener('hidden.bs.modal', function() {
    document.getElementById('agreementFrame').src = '';
  });
});
</script>
