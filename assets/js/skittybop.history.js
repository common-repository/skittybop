jQuery(document).ready(function ($) {
    let history = null;
    let toBeDeleted = null;
    let fromDate = null;
    let toDate = null;

    history = loadHistoryTable();
    history.ajax.reload();

    $('.dt-start').first().append('<div class="dt-search dt-date"><input type="search" class="dt-input" id="from_date" placeholder="From" aria-controls="skittybopHistoryTable"></div>');
    $('.dt-start').first().append('<div class="dt-search dt-date"><input type="search" class="dt-input" id="to_date" placeholder="To" aria-controls="skittybopHistoryTable"></div>');

    fromDate = new DateTime('#from_date', { format: args.datetime_format.toMoment() });
    toDate = new DateTime('#to_date', { format: args.datetime_format.toMoment() });

    document.querySelectorAll('#from_date, #to_date').forEach((el) => {
        el.addEventListener('change', () => history.draw());
    });

    function loadHistoryTable() {
        let buttons = [];
        let columnsDef = [];
        let columns = [];
        let order = [];

        if (args.is_administrator) {
            buttons.push({
                text: '<img class="red" src="' + args.img.trash + '">',
                action: function (e, dt, node, config) {
                    const rows = dt.rows({selected: true}).data().toArray();
                    const ids = rows.map(r => parseInt(r.id));

                    skittybopOpenConfirmDeleteDialog(dt, ids);
                },
                enabled: false
            });

            order = [[2, 'desc']];

            columns = [
                {data: 'responsive'},
                {data: 'select'},
                {data: 'id'},
                {data: 'operator'},
                {data: 'status'},
                {data: 'room'},
                {data: 'started'},
                {data: 'ended'},
            ];

            columnsDef = [
                {className: 'dt-center', targets: [2, 4, 5, 6, 7]},
                {
                    className: 'dtr-control',
                    orderable: false,
                    targets: 0
                },
                {
                    orderable: false,
                    render: DataTable.render.select(),
                    targets: 1,
                    width: '2%'
                },
                {width: '5%', targets: 2},
                {width: '33%', targets: 3},
                {
                    width: '10%',
                    targets: 4,
                    render: statusRenderer
                },
                {width: '20%', targets: 5},
                {
                    targets: 6,
                    width: '20%',
                    render: datetimeRenderer
                },
                {
                    targets: 7,
                    width: '10%',
                    render: durationRenderer
                }
            ];

            $("#skittybopConfirmDeleteButton").click(function () {
                if (!toBeDeleted) {
                    return;
                }

                const data = {
                    'action': 'skittybop_delete_calls',
                    '_wpnonce': $("input#_wpnonce_skittybop_delete_calls").val(),
                    'calls': toBeDeleted
                };

                $.ajax({
                    type: "POST",
                    url: args.ajaxurl,
                    data: data,
                    success: function (data) {
                        skittybopCloseConfirmDeleteDialog();
                        toBeDeleted = null;
                        history.processing(false);
                        history.ajax.reload();
                    },
                    error: function (data) {
                        skittybopCloseConfirmDeleteDialog();
                        toBeDeleted = null;
                        history.processing(false);
                        history.ajax.reload();
                    }
                });
            });

            $("#skittybopCancelDeleteButton").click(function () {
                skittybopCloseConfirmDeleteDialog();
            });
        } else {
            order = [[1, 'desc']];
            columns = [
                {data: 'responsive'},
                {data: 'id'},
                {data: 'operator'},
                {data: 'status'},
                {data: 'room'},
                {data: 'started'},
                {data: 'ended'},
            ];

            columnsDef = [
                {className: 'dt-center', targets: [1, 3, 4, 5, 6]},
                {
                    className: 'dtr-control',
                    orderable: false,
                    targets: 0
                },
                {width: '7%', targets: 1},
                {width: '33%', targets: 2},
                {
                    width: '10%',
                    targets: 3,
                    render: statusRenderer
                },
                {width: '20%', targets: 4},
                {
                    targets: 5,
                    width: '20%',
                    render: datetimeRenderer
                },
                {
                    targets: 6,
                    width: '10%',
                    render: durationRenderer
                }
            ];
        }

        //date range filtering function for the start date of the call
        DataTable.ext.search.push(function (settings, data, dataIndex) {
            let from = fromDate.val();
            let to = toDate.val();
            let date = new Date(data[5]);

            return (from === null && to === null) || (from === null && date <= to) ||
                (from <= date && to === null) || (from <= date && date <= to);
        });

        let format = args.datetime_format.toMoment();
        return new DataTable('#skittybopHistoryTable', {
            jQueryUI: true,
            processing: true,
            serverSide: true,
            deferRender: true,
            pageLength: jQuery('#per_page').val(),
            responsive: {
                details: {
                    type: 'column'
                }
            },
            ajax: {
                url: args.ajaxurl,
                data: function (d) {
                    d.action = 'skittybop_fetch_calls';
                    d._wpnonce = $("input#_wpnonce_skittybop_fetch_calls").val();
                    d.from_date = moment($("input#from_date").val(), format).toISOString();
                    d.to_date = moment($("input#to_date").val(), format).toISOString();
                }
            },
            layout: {
                topStart: 'search',
                topEnd: {
                    buttons: buttons
                },
                bottomStart: {
                   info: {}
                },
            },
            select: {
                style: 'multi',
                selector: 'td:nth-child(2)'
            },
            columnDefs: columnsDef,
            columns: columns,
            language: {
                info: "_START_ - _END_ of _TOTAL_",
                lengthMenu: '_MENU_',
                infoEmpty: "0 of 0",
                search: "",
                searchPlaceholder: "Search",
                zeroRecords: "No video calls found.",
                emptyTable: "No video calls found.",
                infoFiltered: "",
                select: {
                    rows: {}
                }
            },
            order: order,
        }).on('select', function (e, dt, type, indexes) {
            dt.rows(indexes).every(function (rowIdx, tableLoop, rowLoop) {
                let node = this.cell(rowIdx, 0).node();
                $('input', node).prop('checked', true);
            });
            let selectedRows = dt.rows({selected: true}).count();
            dt.button(0).enable(selectedRows > 0);
        }).on('deselect', function (e, dt, type, indexes) {
            dt.rows(indexes).every(function (rowIdx, tableLoop, rowLoop) {
                let node = this.cell(rowIdx, 0).node();
                $('input', node).prop('checked', false);
            });
            let selectedRows = dt.rows({selected: true}).count();
            dt.button(0).enable(selectedRows > 0);
        });
    }

    function skittybopOpenConfirmDeleteDialog(table, ids) {
        toBeDeleted = ids;
        $("#skittybop-dialog-confirm-delete").dialog(commonDialogOptions);
    }

    function skittybopCloseConfirmDeleteDialog() {
        $("#skittybop-dialog-confirm-delete").dialog('close');
    }

    function statusRenderer(data, type, row, meta) {
        if (!data) {
            return "-";
        }
        if (type === 'display') {
            data = parseInt(data);
            if (data === args.status.pending) {
                return args.lang.pending;
            } else if (data === args.status.accepted) {
                return args.lang.accepted;
            } else if (data === args.status.rejected) {
                return args.lang.rejected;
            } else if (data === args.status.failed) {
                return args.lang.failed;
            } else if (data === args.status.canceled) {
                return args.lang.canceled;
            } else {
                return data;
            }
        }
        return data;
    }

    function datetimeRenderer(data, type, row, meta) {
        if (!data) {
            return "-";
        }
        return moment.utc(data).local().format(args.datetime_format.toMoment());
    }

    function durationRenderer(data, type, row, meta) {
        if (!data) {
            return "-";
        }

        let started = moment(row['started']);
        let ended = moment(data);

        let duration = started.isValid() && ended.isValid() ? moment.duration(ended.diff(started)) : null;

        return duration ? moment.utc(duration.asMilliseconds()).format("HH:mm:ss") : "-";
    }
});

String.prototype.toMoment = function () {
    const conversions = {
        'd': 'DD',
        'D': 'ddd',
        'j': 'D',
        'l': 'dddd',
        'N': 'E',
        'S': 'o',
        'w': 'e',
        'z': 'DDD',
        'W': 'W',
        'F': 'MMMM',
        'm': 'MM',
        'M': 'MMM',
        'n': 'M',
        't': '',
        'L': '',
        'o': 'YYYY',
        'Y': 'YYYY',
        'y': 'YY',
        'a': 'a',
        'A': 'A',
        'B': '',
        'g': 'h',
        'G': 'H',
        'h': 'hh',
        'H': 'HH',
        'i': 'mm',
        's': 'ss',
        'u': 'SSS',
        'e': 'zz',
        'I': '',
        'O': '',
        'P': '',
        'T': '',
        'Z': '',
        'c': '',
        'r': '',
        'U': 'X',
    };

    return this.replace(/[A-Za-z]+/g, function (match) {
        return conversions[match] || match;
    });
}