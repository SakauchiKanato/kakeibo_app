let mainSwiper;

// ダークモード切り替え
function toggleTheme() {
    const html = document.documentElement;
    const currentTheme = html.getAttribute('data-theme');
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    html.setAttribute('data-theme', newTheme);
    localStorage.setItem('theme', newTheme);

    // アイコンを変更
    const btn = document.querySelector('.theme-toggle');
    btn.textContent = newTheme === 'dark' ? '☀️' : '🌙';
}

// ページ読み込み時にテーマを復元
document.addEventListener('DOMContentLoaded', function () {
    const savedTheme = localStorage.getItem('theme') || 'light';
    document.documentElement.setAttribute('data-theme', savedTheme);
    const btn = document.querySelector('.theme-toggle');
    if (btn) btn.textContent = savedTheme === 'dark' ? '☀️' : '🌙';
});

window.openBudgetModal = function () {
    document.getElementById('budgetModal').style.display = 'flex';
};

window.closeBudgetModal = function () {
    document.getElementById('budgetModal').style.display = 'none';
};

window.openHelpModal = function () {
    document.getElementById('helpModal').style.display = 'flex';
};

window.closeHelpModal = function () {
    document.getElementById('helpModal').style.display = 'none';
};

window.closeModal = function () {
    document.getElementById('editModal').style.display = 'none';
};

window.openAddModal = function () {
    const dateInput = document.getElementById('addDateInput');
    if (dateInput && !dateInput.value) {
        dateInput.value = new Date().toISOString().split('T')[0];
    }
    document.getElementById('addModal').style.display = 'flex';
};

window.closeAddModal = function () {
    document.getElementById('addModal').style.display = 'none';
};

let deleteTargetId = null;

window.confirmDelete = function () {
    const editIdElement = document.getElementById('edit-id');
    deleteTargetId = editIdElement ? editIdElement.value : null;

    console.log('Edit ID Element:', editIdElement);
    console.log('Delete Target ID:', deleteTargetId);

    if (!deleteTargetId || deleteTargetId === '') {
        alert('エラー: 削除対象のIDが取得できませんでした');
        return;
    }

    document.getElementById('deleteConfirmModal').style.display = 'flex';
};

window.closeDeleteConfirm = function () {
    document.getElementById('deleteConfirmModal').style.display = 'none';
    deleteTargetId = null;
};

window.executeDelete = function () {
    console.log('Executing delete for ID:', deleteTargetId);
    if (deleteTargetId && deleteTargetId !== '') {
        window.location.href = 'delete_action.php?id=' + deleteTargetId;
    } else {
        alert('エラー: 削除対象のIDが無効です');
    }
};

// 画面のどこかをクリックした時にモーダルを閉じる処理（既存のものがあれば上書き）
window.onclick = function (event) {
    const bModal = document.getElementById('budgetModal');
    const eModal = document.getElementById('editModal');
    const aModal = document.getElementById('addModal');
    const dModal = document.getElementById('deleteConfirmModal');
    if (event.target == bModal) closeBudgetModal();
    if (event.target == eModal) closeModal();
    if (event.target == aModal) closeAddModal();
    if (event.target == dModal) closeDeleteConfirm();
};

// 予算アラートを取得して表示する関数
window.loadAlerts = function () {
    fetch('check_alerts.php')
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById('alertContainer');
            if (!container) return;

            container.innerHTML = '';

            if (data.alerts && data.alerts.length > 0) {
                data.alerts.forEach(alert => {
                    const alertDiv = document.createElement('div');
                    const bgColor = alert.type === 'danger' ? '#ffebee' :
                        alert.type === 'warning' ? '#fff3e0' : '#e3f2fd';
                    const textColor = alert.type === 'danger' ? '#c62828' :
                        alert.type === 'warning' ? '#e65100' : '#1565c0';

                    alertDiv.style.cssText = `
                        background: ${bgColor};
                        color: ${textColor};
                        padding: 12px 15px;
                        border-radius: 12px;
                        margin-bottom: 10px;
                        font-size: 0.9rem;
                        display: flex;
                        align-items: center;
                        gap: 10px;
                        animation: slideIn 0.3s ease-out;
                    `;
                    alertDiv.innerHTML = `<span style="font-size: 1.2rem;">${alert.icon}</span><span>${alert.message}</span>`;
                    container.appendChild(alertDiv);
                });
            }
        })
        .catch(err => console.error('アラート取得エラー:', err));
};


document.addEventListener('DOMContentLoaded', function () {
    // 1. URLからどのスライドを表示するか決める処理
    const urlParams = new URLSearchParams(window.location.search);
    const startSlide = urlParams.get('slide') !== null ? parseInt(urlParams.get('slide')) : 1;

    // メニューの色を更新する共通関数
    const updateNavUI = (index) => {
        const btns = document.querySelectorAll('.nav-item');
        btns.forEach((btn, i) => btn.classList.toggle('active', i === index));
    };

    // 2. Swiper初期化
    mainSwiper = new Swiper('.swiper', {
        initialSlide: startSlide,
        speed: 400,
        touchStartPreventDefault: false,
        preventClicks: false,
        preventClicksPropagation: false,
        on: {
            slideChange: function () {
                updateNavUI(this.activeIndex);
            }
        }
    });

    updateNavUI(startSlide);
    // ★ここを追加：読み込みが終わったら、URLから「?slide=0」などのパラメータを消す
    // これにより、次に「更新」ボタンを押した時はデフォルトのホーム(1)が開くようになります
    if (urlParams.has('slide')) {
        const cleanUrl = window.location.pathname; // パラメータなしのURLを取得
        window.history.replaceState({}, document.title, cleanUrl);
    }

    // 2. カレンダー初期化
    const calendarEl = document.getElementById('calendar');
    if (calendarEl) {
        const tooltip = document.getElementById('tooltip');
        const calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            locale: 'ja',
            height: 'auto',
            selectable: true, // 選択可能にする
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: ''
            },
            events: window.APP_DATA.events,
            dateClick: function (info) {
                // 日付クリック時に追加モーダルを開く
                document.getElementById('addDateInput').value = info.dateStr;
                document.getElementById('addModal').style.display = 'flex';
            },
            select: function (info) {
                // 選択（長押しやドラッグ）時も追加モーダルを開く
                document.getElementById('addDateInput').value = info.startStr;
                document.getElementById('addModal').style.display = 'flex';
                calendar.unselect(); // 選択状態を解除
            },
            eventClick: function (info) {
                // FullCalendar 6では id, title, startなどは eventの直下にある
                // それ以外のカスタムデータは extendedProps の中にある
                const event = info.event;
                const props = event.extendedProps;

                const editId = event.id;
                document.getElementById('edit-id').value = editId;
                document.getElementById('edit-desc').value = props.description;
                document.getElementById('edit-amount').value = event.title.replace('円', '').replace(/,/g, '');
                document.getElementById('edit-sat').value = props.satisfaction;
                if (props.categoryId) {
                    document.getElementById('edit-category').value = props.categoryId;
                }
                document.getElementById('editModal').style.display = 'flex';
            },
            eventMouseEnter: function (info) {
                tooltip.innerHTML = `${info.event.extendedProps.description}<br>${info.event.title}`;
                tooltip.style.display = 'block';
            },
            eventMouseMove: function (info) {
                // clientX/Y を使い、スクロールを考慮した viewport 基準で配置 (fixed用)
                tooltip.style.left = (info.jsEvent.clientX + 10) + 'px';
                tooltip.style.top = (info.jsEvent.clientY + 10) + 'px';
            },
            eventMouseLeave: function () { tooltip.style.display = 'none'; }
        });
        calendar.render();
    }

    // 3. グラフ初期化
    // カテゴリー別円グラフ
    const categoryPieCtx = document.getElementById('categoryPieChart');
    if (categoryPieCtx && window.APP_DATA.categoryData && window.APP_DATA.categoryData.length > 0) {
        new Chart(categoryPieCtx, {
            type: 'doughnut',
            data: {
                labels: window.APP_DATA.categoryLabels,
                datasets: [{
                    data: window.APP_DATA.categoryData,
                    backgroundColor: window.APP_DATA.categoryColors
                }]
            },
            options: {
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
    }

    // 満足度別円グラフ
    const pieCtx = document.getElementById('pieChart');
    if (pieCtx) {
        new Chart(pieCtx, {
            type: 'doughnut',
            data: {
                labels: ['星1', '星2', '星3', '星4', '星5'],
                datasets: [{ data: window.APP_DATA.pie, backgroundColor: ['#e0e0e0', '#90a4ae', '#4db6ac', '#ffca28', '#ff9800'] }]
            },
            options: {
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
    }

    const barCtx = document.getElementById('barChart');
    if (barCtx) {
        new Chart(barCtx, {
            type: 'bar',
            data: {
                labels: window.APP_DATA.barLabels,
                datasets: [{ label: '支出(円)', data: window.APP_DATA.barData, backgroundColor: '#667eea' }]
            },
            options: { maintainAspectRatio: false }
        });
    }

    // 4. 予算アラートの読み込み
    loadAlerts();

    // 5. レシート画像アップロード処理
    const receiptInput = document.getElementById('receiptImage');
    if (receiptInput) {
        receiptInput.addEventListener('change', function (e) {
            const file = e.target.files[0];
            if (!file) return;

            const preview = document.getElementById('receiptPreview');
            const previewImg = document.getElementById('previewImg');
            const ocrStatus = document.getElementById('ocrStatus');

            const reader = new FileReader();
            reader.onload = function (event) {
                previewImg.src = event.target.result;
                preview.style.display = 'block';
                ocrStatus.textContent = '📊 画像を解析中...';
                ocrStatus.style.color = '#667eea';
            };
            reader.readAsDataURL(file);

            const formData = new FormData();
            formData.append('receipt_image', file);

            fetch('upload_receipt.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    console.log('OCR Response:', data);

                    if (data.success) {
                        ocrStatus.textContent = '✅ 解析完了！フォームに自動入力しました';
                        ocrStatus.style.color = '#10b981';

                        if (data.amount) {
                            document.getElementById('amountInput').value = data.amount;
                        }
                        if (data.description) {
                            document.getElementById('descriptionInput').value = data.description;
                        }
                    } else {
                        ocrStatus.innerHTML = '⚠️ ' + (data.error || '解析に失敗しました');
                        if (data.details) {
                            ocrStatus.innerHTML += '<br><small>' + data.details + '</small>';
                        }
                        if (data.raw_output) {
                            console.error('OCR Raw Output:', data.raw_output);
                        }
                        ocrStatus.style.color = '#ef4444';
                    }
                })
                .catch(err => {
                    ocrStatus.textContent = '❌ エラーが発生しました';
                    ocrStatus.style.color = '#ef4444';
                    console.error('OCRエラー:', err);
                });
        });
    }
});