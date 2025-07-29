document.addEventListener('DOMContentLoaded', function() {
    const mainCats = document.querySelectorAll('.moderno-main-category');
    const subLists = document.querySelectorAll('.moderno-subcategory-list');

    mainCats.forEach(cat => {
        cat.addEventListener('click', function () {
            const parentId = cat.getAttribute('data-category-id');
            const targetList = document.querySelector(
                `.moderno-subcategory-list[data-parent-id="${parentId}"]`
            );

            const isAlreadyOpen = cat.classList.contains('open');

            // Close all subcategory lists
            subLists.forEach(list => list.style.display = 'none');
            mainCats.forEach(c => c.classList.remove('open'));

            // If it was not already open, open it now
            if (!isAlreadyOpen && targetList) {
                targetList.style.display = 'grid';
                cat.classList.add('open');
            }
        });
    });
});

