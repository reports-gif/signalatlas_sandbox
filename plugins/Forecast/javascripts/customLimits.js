(function () {

    const defaultGeneral = "8";
    const defaultMonth = "3";

    const removePeriods = ["year", "week"];
    const removeValues = ["120", "365", "500", "30"];

    const cleanupForecastUI = function () {
        const $widget = $('#widgetForecastforecastWidget');
        if ($widget.length === 0) return;

        // --- Aktuelle Periode ermitteln ---
        const activePeriod = $widget
            .find('.dataTablePeriods li.active a')
            .data('period');

        const allowedDefault = (activePeriod === "month")
            ? defaultMonth
            : defaultGeneral;

        // --- Perioden entfernen ---
        removePeriods.forEach(function (period) {
            $widget
                .find('.dataTablePeriods li a[data-period="' + period + '"]')
                .closest('li')
                .remove();
        });

        // --- Dropdown bearbeiten ---
        $widget.find('.select-wrapper input.select-dropdown').each(function () {

            const $input = $(this);
            const dropdownId = $input.attr('data-activates');
            let $dropdownList = $('#' + dropdownId);

            if ($dropdownList.length === 0) {
                $dropdownList = $('.select-dropdown.dropdown-content');
            }

            // Unerwünschte Werte entfernen
            $dropdownList.find('li span').each(function () {
                const val = $(this).text().trim();
                if (removeValues.indexOf(val) !== -1) {
                    $(this).closest('li').remove();
                }
            });

            const currentVal = $input.val() ? $input.val().trim() : "";

            // Wenn aktueller Wert ungültig → Default setzen
            if (removeValues.indexOf(currentVal) !== -1 || currentVal === "") {
                $input.val(allowedDefault);
                $input.trigger('change');
            }
        });
    };

    const setupObserver = function () {
        const targetNode = document.getElementById('widgetForecastforecastWidget');

        if (!targetNode) {
            setTimeout(setupObserver, 500);
            return;
        }

        const observer = new MutationObserver(function () {
            cleanupForecastUI();
        });

        observer.observe(targetNode, {
            childList: true,
            subtree: true
        });

        cleanupForecastUI();
    };

    $(document).ready(function () {
        setupObserver();
    });

})();