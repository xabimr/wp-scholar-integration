(function () {
    'use strict';
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.wp-scholar-container').forEach(function (box) {
            var select = box.querySelector('.wp-scholar-sort-select');
            var list = box.querySelector('.wp-scholar-publications');
            if (!select || !list) { return; }

            var items = Array.prototype.slice.call(list.children);
            items.forEach(function (li, i) { li.dataset.pos = i; });

            function num(li, key) { return parseInt(li.dataset[key], 10) || 0; }

            var sorters = {
                year_desc:  function (a, b) { return num(b, 'year') - num(a, 'year') || num(b, 'cited') - num(a, 'cited'); },
                year_asc:   function (a, b) {
                    // Sin año al final
                    var ya = num(a, 'year') || 9999, yb = num(b, 'year') || 9999;
                    return ya - yb || num(b, 'cited') - num(a, 'cited');
                },
                cited_desc: function (a, b) { return num(b, 'cited') - num(a, 'cited') || num(b, 'year') - num(a, 'year'); }
            };

            select.addEventListener('change', function () {
                var fn = sorters[select.value];
                if (!fn) { return; }
                items.slice().sort(function (a, b) {
                    return fn(a, b) || num(a, 'pos') - num(b, 'pos');
                }).forEach(function (li) { list.appendChild(li); });
            });
        });
    });
})();
