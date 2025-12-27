
window.openGoalModal = function () {
    document.getElementById('goalModal').style.display = 'flex';
};

window.closeGoalModal = function () {
    document.getElementById('goalModal').style.display = 'none';
};

// 画面のどこかをクリックした時にモーダルを閉じる
const originalOnClick = window.onclick;
window.onclick = function (event) {
    if (originalOnClick) originalOnClick(event);
    const modal = document.getElementById('goalModal');
    if (event.target == modal) closeGoalModal();
};
