document.addEventListener("DOMContentLoaded", () => {

    fetch("pages/add_charger_page.php")
        .then(response => response.text())
        .then(html => {
            document.getElementById("page-content").innerHTML = html;
        })
        .catch(error => {
            document.getElementById("page-content").innerHTML =
                "<h2>Failed to load page.</h2>";

            console.error(error);
        });

});