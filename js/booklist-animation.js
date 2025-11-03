
document.addEventListener('DOMContentLoaded', function() {
    // 选择所有书单列表中的项目
    const bookItems = document.querySelectorAll('.movielist li');

    if (bookItems.length > 0) {
        bookItems.forEach((item, index) => {
            // 为每个项目计算并应用一个递增的动画延迟
            // 0.07秒的间隔创造一种流畅的、不突兀的依次出现效果
            item.style.animationDelay = (index * 0.07) + 's';
        });
    }
});
