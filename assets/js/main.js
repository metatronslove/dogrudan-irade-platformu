// Doğrudan İrade Platformu - Ana JavaScript Fonksiyonları

document.addEventListener('DOMContentLoaded', function() {
    // Oy verme butonlarına animasyon ekle
    initVoteButtons();
    
    // Süre dolmuş oylamaları kontrol et
    checkExpiredPolls();
    
    // Kullanıcı deneyimi iyileştirmeleri
    enhanceUserExperience();
});

// Oy verme butonlarını başlat
function initVoteButtons() {
    const voteButtons = document.querySelectorAll('.btn-support, .btn-negative');
    
    voteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            // Butona tıklama animasyonu
            this.style.transform = 'scale(0.95)';
            setTimeout(() => {
                this.style.transform = '';
            }, 150);
        });
    });
}

// Süresi dolmuş oylamaları kontrol et
function checkExpiredPolls() {
    const endTimeElements = document.querySelectorAll('[data-end-time]');
    const now = new Date().getTime();
    
    endTimeElements.forEach(element => {
        const endTime = new Date(element.dataset.endTime).getTime();
        if (endTime < now) {
            // Oylama süresi dolmuş
            element.closest('.poll-item').classList.add('expired');
            const voteBtn = element.closest('.poll-item').querySelector('a.btn');
            if (voteBtn) {
                voteBtn.classList.remove('btn-primary');
                voteBtn.classList.add('btn-secondary', 'disabled');
                voteBtn.innerHTML = '⏰ Süre Doldu';
                voteBtn.removeAttribute('href');
            }
        }
    });
}

// Kullanıcı deneyimi iyileştirmeleri
function enhanceUserExperience() {
    // Form doğrulama
    const forms = document.querySelectorAll('form[needs-validation]');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!this.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
            }
            this.classList.add('was-validated');
        });
    });
    
    // Toast mesajları (başarı/hata mesajları için)
    window.showToast = function(message, type = 'info') {
        const toastContainer = document.getElementById('toast-container') || createToastContainer();
        
        const toastId = 'toast-' + Date.now();
        const toast = document.createElement('div');
        toast.className = `toast align-items-center text-bg-${type} border-0`;
        toast.id = toastId;
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'assertive');
        toast.setAttribute('aria-atomic', 'true');
        
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" 
                        data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        `;
        
        toastContainer.appendChild(toast);
        const bsToast = new bootstrap.Toast(toast);
        bsToast.show();
        
        toast.addEventListener('hidden.bs.toast', function() {
            this.remove();
        });
    };
    
    function createToastContainer() {
        const container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'toast-container position-fixed top-0 end-0 p-3';
        container.style.zIndex = '9999';
        document.body.appendChild(container);
        return container;
    }
    
    // Auto-logout timer (30 dakika inaktivite)
    let idleTimer;
    function resetIdleTimer() {
        clearTimeout(idleTimer);
        idleTimer = setTimeout(logoutWarning, 30 * 60 * 1000); // 30 dakika
    }
    
    function logoutWarning() {
        if (confirm('Oturumunuz süresi dolmak üzere. Devam etmek istiyor musunuz?')) {
            resetIdleTimer();
        } else {
            window.location.href = 'cikis.php';
        }
    }
    
    // Kullanıcı aktivitelerini dinle
    ['mousemove', 'keypress', 'click', 'scroll'].forEach(event => {
        document.addEventListener(event, resetIdleTimer);
    });
    
    resetIdleTimer(); // Timer'ı başlat
}

// API istekleri için yardımcı fonksiyon
window.apiRequest = async function(url, method = 'GET', data = null) {
    const options = {
        method: method,
        headers: {
            'Content-Type': 'application/json',
        },
        credentials: 'same-origin'
    };
    
    if (data && (method === 'POST' || method === 'PUT')) {
        options.body = JSON.stringify(data);
    }
    
    try {
        const response = await fetch(url, options);
        const result = await response.json();
        
        if (!response.ok) {
            throw new Error(result.message || 'Bir hata oluştu');
        }
        
        return result;
    } catch (error) {
        console.error('API Hatası:', error);
        showToast(error.message, 'danger');
        throw error;
    }
};

// Oy verme fonksiyonu (yeniden tanımlanabilir)
window.vote = function(adayId, type) {
    if (!confirm(`${type === 'destek' ? 'Destek' : 'Negatif'} oyunuzu güncellemek istediğinize emin misiniz?`)) {
        return;
    }

    const formData = new FormData();
    formData.append('action', type);
    formData.append('aday_id', adayId);

    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Oyunuz başarıyla kaydedildi!', 'success');
            // Sayfayı yenile
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(data.message || 'Bir hata oluştu', 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('İşlem sırasında bir hata oluştu.', 'danger');
    });
};

// Canlı sonuç güncellemesi
function startLiveResultsUpdate(oylamaId) {
    if (!window.liveUpdateInterval) {
        window.liveUpdateInterval = setInterval(() => {
            fetch(`api.php?action=live_results&oylama_id=${oylamaId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateResultsUI(data.results);
                    }
                })
                .catch(error => console.error('Live update error:', error));
        }, 30000); // 30 saniyede bir
    }
}

// Sonuçları güncelle
function updateResultsUI(results) {
    // Bu fonksiyon sonuç sayfasındaki verileri günceller
    const resultsContainer = document.getElementById('results-container');
    if (resultsContainer) {
        // UI güncelleme mantığı burada
        console.log('Results updated:', results);
    }
}

// Sayfadan ayrılırken interval'i temizle
window.addEventListener('beforeunload', function() {
    if (window.liveUpdateInterval) {
        clearInterval(window.liveUpdateInterval);
    }
});
