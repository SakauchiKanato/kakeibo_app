let mainSwiper;

window.openBudgetModal = function() {
    document.getElementById('budgetModal').style.display = 'flex';
};

window.closeBudgetModal = function() {
    document.getElementById('budgetModal').style.display = 'none';
};

window.closeModal = function() {
    document.getElementById('editModal').style.display = 'none';
};

// 画面のどこかをクリックした時にモーダルを閉じる処理（既存のものがあれば上書き）
window.onclick = function(event) {
    const bModal = document.getElementById('budgetModal');
    const eModal = document.getElementById('editModal');
    if (event.target == bModal) closeBudgetModal();
    if (event.target == eModal) closeModal();
};

document.addEventListener('DOMContentLoaded', function() {
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
            events: window.APP_DATA.events,
            eventClick: function(info) {
                const props = info.event.extendedProps;
                document.getElementById('edit-id').value = props.id;
                document.getElementById('edit-desc').value = props.description;
                document.getElementById('edit-amount').value = info.event.title.replace('円', '');
                document.getElementById('edit-sat').value = props.satisfaction;
                document.getElementById('editModal').style.display = 'flex';
            },
            eventMouseEnter: function(info) {
                tooltip.innerHTML = `${info.event.extendedProps.description}<br>${info.event.title}`;
                tooltip.style.display = 'block';
            },
            eventMouseMove: function(info) {
                tooltip.style.left = (info.jsEvent.clientX + 15) + 'px';
                tooltip.style.top = (info.jsEvent.clientY + 15) + 'px';
            },
            eventMouseLeave: function() { tooltip.style.display = 'none'; }
        });
        calendar.render();
    }

    // 3. グラフ初期化
    const pieCtx = document.getElementById('pieChart');
    if (pieCtx) {
        new Chart(pieCtx, {
            type: 'doughnut',
            data: {
                labels: ['星1', '星2', '星3', '星4', '星5'],
                datasets: [{ data: window.APP_DATA.pie, backgroundColor: ['#e0e0e0', '#90a4ae', '#4db6ac', '#ffca28', '#ff9800'] }]
            },
            options: { maintainAspectRatio: false }
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
});