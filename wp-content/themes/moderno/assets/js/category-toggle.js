document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll(".moderno-main-category").forEach(function (cat) {
        cat.addEventListener("click", function () {
            this.classList.toggle("open");
        });
    });
});
