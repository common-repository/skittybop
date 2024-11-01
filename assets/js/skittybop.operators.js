jQuery(document).ready(function ($) {

    loadOperatorsTable();

    function loadOperatorsTable() {
        let columnsDef = [];
        let columns = [];
        let order = [];

        order = [[2, 'desc']];

        columns = [
            { data: 'responsive' },
            { data: 'user_name' },
            { data: 'user_status'},
        ];

        columnsDef = [
            {className: 'dt-center', targets: [2]},
            {
                className: 'dtr-control',
                orderable: false,
                targets: 0
            },
            {width: '43%', targets: 1},
            {
                width: '10%',
                targets: 2,
                render: statusRenderer
            }
        ];

        return new DataTable('#skittybopOperatorsTable', {
            jQueryUI: true,
            processing: true,
            deferRender: true,
            pageLength: jQuery('#per_page').val(),
            responsive: {
                details: {
                    type: 'column'
                }
            },
            ajax: {
                url: args.ajaxurl,
                data: {
                    'action': 'skittybop_fetch_operators',
                    '_wpnonce': $("input#_wpnonce_skittybop_fetch_operators").val(),
                }
            },
            layout: {
                topStart: 'search',
                topEnd: null,
                bottomStart: {
                    info: {}
                },
            },
            columnDefs: columnsDef,
            columns: columns,
            language: {
                info: "_START_ - _END_ of _TOTAL_",
                lengthMenu: '_MENU_',
                infoEmpty: "0 of 0",
                search: '',
                searchPlaceholder: "Search",
                zeroRecords: "No operators found.",
                emptyTable: "No operators found.",
                infoFiltered: "",
                select: {
                    rows: {}
                }
            },
            order: order,
        });
    }

    function statusRenderer(data, type, row, meta) {
        if (type === 'display') {
            if (data === true) {
                return '<div class="skittybop-logged-in skittybop-green"/>'
            } else {
                return '<div class="skittybop-logged-out skittybop-red"/>'
            }
        }
        return data;
    }
});