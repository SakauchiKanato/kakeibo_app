
window.openRecurringModal = function () {
    document.getElementById('recurringModal').style.display = 'flex';
};

window.closeRecurringModal = function () {
    document.getElementById('recurringModal').style.display = 'none';
};

// 画面のどこかをクリックした時にモーダルを閉じる
const originalOnClickRecurring = window.onclick;
window.onclick = function (event) {
    if (originalOnClickRecurring) originalOnClickRecurring(event);
    const modal = document.getElementById('recurringModal');
    if (event.target == modal) closeRecurringModal();
};
