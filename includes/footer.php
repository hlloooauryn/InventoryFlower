    </main>

    <!-- Bootstrap Modal Konfirmasi Hapus -->
    <div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteConfirmModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content modal-pastel border-0 shadow">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title font-playfair fs-5" id="deleteConfirmModalLabel">Konfirmasi Hapus</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body text-secondary small py-3">
                    Item <strong id="deleteProductName" class="text-dark"></strong> akan dihapus permanen.
                </div>
                <div class="modal-footer border-0 pt-0 d-flex gap-2">
                    <button type="button" class="btn btn-pastel-outline btn-sm flex-fill" data-bs-dismiss="modal">Batal</button>
                    <button type="button" id="confirmDeleteSubmit" class="btn btn-pastel-danger btn-sm flex-fill">Hapus</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5.3 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script>
    let activeDeleteForm = null;
    const deleteModalEl = document.getElementById('deleteConfirmModal');
    const deleteModal = deleteModalEl ? new bootstrap.Modal(deleteModalEl) : null;

    function openDeleteModal(form, itemName) {
        activeDeleteForm = form;
        const nameEl = document.getElementById('deleteProductName');
        if (nameEl) nameEl.textContent = itemName ? `"${itemName}"` : 'ini';
        if (deleteModal) deleteModal.show();
        return false;
    }

    document.getElementById('confirmDeleteSubmit')?.addEventListener('click', function() {
        if (activeDeleteForm) {
            activeDeleteForm.submit();
        }
    });

    // Preview gambar saat dipilih
    function previewProductImage(input, previewContainerId, previewImgId) {
        const file = input.files && input.files[0];
        const container = document.getElementById(previewContainerId);
        const img = document.getElementById(previewImgId);
        if (file && container && img) {
            const reader = new FileReader();
            reader.onload = function(e) {
                img.src = e.target.result;
                container.classList.remove('d-none');
            };
            reader.readAsDataURL(file);
        }
    }
    </script>
</body>
</html>
