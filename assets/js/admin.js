//Admin Includes
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('active');
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
    }
}

function validateFileSize(input) {
    const errorElement = document.getElementById('fileError');
    const maxSizeBytes = 10 * 1024 * 1024;

    if (input.files && input.files[0]) {
        if (input.files[0].size > maxSizeBytes) {
            if (errorElement) {
                errorElement.textContent = "File size exceeds 10MB limit! Please select a smaller image.";
                errorElement.style.display = "block";
            }
            input.value = ""; 
        } else if (errorElement) {
            errorElement.style.display = "none";
        }
    }
}

