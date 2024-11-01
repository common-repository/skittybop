let skittybopOutgoingDialog = null;
let skittybopOutgoingCall = null;

let skittybopIncomingDialog = null;
let skittybopIncomingCall = null;

let skittybopActiveCallDialog = null;
let skittybopActiveCall = null;
let skittybopActiveCallJWT = null;
let openingPopOutWindow = false;
let isPopOutWindow = false;

let skittybopCallAudio = new Audio(args.plugin_url + '/assets/audio/call.mp3');
skittybopCallAudio.loop = true;

const skittybopPopupTarget = "skittybopPopUp";
const wWidth = jQuery(window).width();
const maxWidth = 700;
const width = wWidth < maxWidth + 50 ? 0.9 * wWidth : maxWidth;
const timeout = 60000;
const commonDialogOptions = {
    closeOnEscape: false,
    draggable: true,
    resizable: false,
    height: "auto",
    width: width,
    modal: false,
    classes: {"ui-dialog": "skittybop-dialog"},
};

jQuery(document).ready(function ($) {
    $("#skittybopRejectCallButton").click(function () {
        if (skittybopIncomingCall) {
            skittybopRejectCall(skittybopIncomingCall);
        } else {
            skittybopClosePendingCallDialogs();
        }
    });

    $("#skittybopAcceptCallButton").click(function () {
        if (skittybopIncomingCall) {
            skittybopAcceptCall(skittybopIncomingCall);
        } else {
            skittybopClosePendingCallDialogs();
        }
    });

    $("#skittybopCancelCallButton").click(function () {
        if (skittybopOutgoingCall) {
            skittybopCancelCall(skittybopOutgoingCall);
            skittybopClosePendingCallDialogs();
        } else {
            skittybopClosePendingCallDialogs();
        }
    });

    $("#skittybopCloseAnsweredCallButton").click(function () {
        skittybopCloseAnsweredCallDialog();
    });

    $("#skittybopCloseNoOperatorButton").click(function () {
        skittybopCloseNoOperatorDialog();
    });

    $("#skittybopCloseServiceUnavailableButton").click(function () {
        skittybopCloseServiceUnavailableDialog();
    });

    $('#' + args.buttons.skittybop).click(function () {
        if (skittybopActiveCallDialog || skittybopIncomingDialog || skittybopOutgoingDialog) {
            return;
        }

        const data = {
            'action': 'skittybop_call',
            '_wpnonce': $("input#_wpnonce_skittybop_call").val()
        };

        jQuery.ajax({
            type: "POST",
            url: args.ajaxurl,
            data: data,
            success: function (data) {
                let response = JSON.parse(data);
                let room = response.room;
                if (room) {
                    skittybopOpenOutgoingCallDialog(room)
                } else {
                    skittybopOpenNoOperatorDialog();
                }
            },
            error: function (data) {
                console.error("failed to start call", data)
            }
        });
    });

    $(window).on("beforeunload", function() {
        //use the beacon api to mark the active video conference end upon browser closing
        skittybopMarkCallTimestamp(skittybopActiveCall, false, true, true);
    })
});

jQuery(document).on('heartbeat-send', function (event, data) {
    if (skittybopOutgoingCall) {
        data.skittybop_check_outgoing_call = skittybopOutgoingCall;
    } else if (args.is_operator) {
        data.skittybop_check_incoming_call = true;
    }
    console.debug('heartbeat-send', data);
});

jQuery(document).on('heartbeat-tick', function (event, data) {
    console.debug('heartbeat-tick', data);

    if (data.skittybop_join_outgoing_call && skittybopOutgoingDialog) {
        const call = JSON.parse(data.skittybop_join_outgoing_call);
        if (call?.room) {
            const jwt = data?.jwt;
            if (jwt) {
                skittybopOpenActiveCallDialog(call.room, call.jwt)
            } else {
                skittybopCancelCall(call.room);
                skittybopOpenServiceUnavailableDialog();
            }
        }
    } else if (data.skittybop_join_incoming_call) {
        skittybopOpenIncomingCallDialog(data.skittybop_join_incoming_call);
    }
});

jQuery(document).on('heartbeat-error', function (e, jqXHR, textStatus, error) {
    console.log(error);
});

function skittybopOpenOutgoingCallDialog(room) {
    if (!room || skittybopOutgoingCall || skittybopActiveCall) {
        return;
    }
    skittybopOutgoingCall = room;

    let timeoutId = null;
    skittybopOutgoingDialog = jQuery("#skittybop-dialog-outgoing").dialog({
        ...commonDialogOptions,
        open: function (event, ui) {
            skittybopCallAudio.play();
            wp.heartbeat.interval('fast');
        },
        close: function (event, ui) {
            skittybopOutgoingCall = null;
            skittybopOutgoingDialog = null;
            skittybopCallAudio.pause();
            skittybopCallAudio.currentTime = 0;

            if (timeoutId) {
                clearTimeout(timeoutId);
            }

            wp.heartbeat.interval('standard');
        }
    });

    timeoutId = setTimeout(() => {
        skittybopFailCall(room);
    }, timeout);

    console.log(skittybopOutgoingCall);
}

function skittybopOpenIncomingCallDialog(room) {
    if (!room || skittybopIncomingCall || skittybopActiveCall) {
        return;
    }
    skittybopIncomingCall = room;
    let timeoutId = null;
    skittybopIncomingDialog = jQuery("#skittybop-dialog-incoming").dialog({
        ...commonDialogOptions,
        open: function (event, ui) {
            skittybopCallAudio.play();
            wp.heartbeat.interval('fast');
        },
        close: function (event, ui) {
            skittybopIncomingCall = null;
            skittybopIncomingDialog = null;
            skittybopCallAudio.pause();
            skittybopCallAudio.currentTime = 0;

            if (timeoutId) {
                clearTimeout(timeoutId);
            }

            wp.heartbeat.interval('standard');
        }
    });

    timeoutId = setTimeout(() => {
        skittybopFailCall(room);
    }, timeout);

    console.log('open incoming call dialog', room);
}

function skittybopClosePendingCallDialogs() {
    console.log('closing pending dialogues');
    if (skittybopIncomingDialog) {
        skittybopIncomingDialog.dialog('close');
    }
    if (skittybopOutgoingDialog) {
        skittybopOutgoingDialog.dialog('close');
    }
}

function skittybopOpenActiveCallDialog(room, jwt) {
    if (!room || !jwt || skittybopActiveCall) {
        return;
    }

    skittybopActiveCall = room;
    skittybopActiveCallJWT = jwt;
    openingPopOutWindow = false;

    skittybopDestroyActiveClient();
    skittybopClosePendingCallDialogs();

    const maxWidth = 900;
    const width = wWidth < maxWidth + 50 ? 0.9 * wWidth : maxWidth;
    const wHeight = jQuery(window).height();
    const maxHeight = 700;
    const height = wHeight < maxHeight + 50 ? 0.8 * wHeight : maxHeight;

    skittybopActiveCallDialog = jQuery("#skittybop-dialog-call").dialog({
        ...commonDialogOptions,
        resizable: true,
        width: width,
        height: height,
        open: function (event, ui) {
            skittybopConnectToJitsiServer(room, jwt);
            skittybopPositionDialogue();
        },
        close: function (event, ui) {
            skittybopActiveCall = null;
            skittybopActiveCallJWT = null;
            skittybopActiveCallDialog = null;
        }
    });

    skittybopActiveCallDialog.data( "uiDialog" )._title = function(title) {
        title.html( title.html() + this.options.title );
        jQuery("#skittybopOpenPopout").click(function () {
            popOutVideoCall(skittybopActiveCall, skittybopActiveCallJWT);
            skittybopHangupActiveCall();
        });
    };

    skittybopActiveCallDialog.dialog('option', 'title', '<span title="' + args.lang.pop_out +
        '" id="skittybopOpenPopout" class="ui-icon ui-icon-popout"></span>');
}

function skittybopCloseActiveCallDialog() {
    if (skittybopActiveCallDialog) {
        skittybopActiveCallDialog.dialog('close');
    }
}

function skittybopOpenAnsweredCallDialog() {
    skittybopCallAudio.pause();
    skittybopCallAudio.currentTime = 0;

    skittybopClosePendingCallDialogs();

    jQuery("#skittybop-dialog-answered").dialog(commonDialogOptions);
}

function skittybopCloseAnsweredCallDialog() {
    jQuery("#skittybop-dialog-answered").dialog('close');
}

function skittybopOpenNoOperatorDialog() {
    jQuery("#skittybop-dialog-no-operator").dialog(commonDialogOptions);
}

function skittybopCloseNoOperatorDialog() {
    jQuery("#skittybop-dialog-no-operator").dialog('close');
}

function skittybopOpenServiceUnavailableDialog() {
    skittybopCallAudio.pause();
    skittybopCallAudio.currentTime = 0;

    skittybopClosePendingCallDialogs();

    jQuery("#skittybop-dialog-service-unavailable").dialog(commonDialogOptions);
}

function skittybopCloseServiceUnavailableDialog() {
    jQuery("#skittybop-dialog-service-unavailable").dialog('close');
}


function skittybopDestroyActiveClient() {
    if (window.skittybop) {
        window.skittybop.dispose();
        window.skittybop = null;
    }
}

function skittybopHangupActiveCall() {
    if (window.skittybop) {
        window.skittybop.executeCommand('hangup');
    }
}

function skittybopConnectToJitsiServer(room, jwt) {
    let params = args.jitsi;

    const options = {
        "roomName": room,
        "width": params.width,
        "height": params.height,
        "parentNode": document.querySelector("#skittybopVideoCall"),
        "jwt": jwt,
        "configOverwrite": {
            "startAudioOnly": params.start_audio_only,
            "defaultLanguage": params.default_language,
            "prejoinPageEnabled": false,
            "deeplinking": {
                "disabled": params.mobile_open_in_browser
            }
        },
        "interfaceConfigOverwrite": {
            "filmStripOnly": params.film_strip_only,
            "DEFAULT_BACKGROUND": params.background_color,
            "DEFAULT_REMOTE_DISPLAY_NAME": "",
            "SHOW_JITSI_WATERMARK": params.show_watermark,
            "SHOW_WATERMARK_FOR_GUESTS": params.show_watermark,
            "SHOW_BRAND_WATERMARK": params.show_brand_watermark,
            "BRAND_WATERMARK_LINK": params.brand_watermark_link,
            "LANG_DETECTION": true,
            "CONNECTION_INDICATOR_DISABLED": false,
            "VIDEO_QUALITY_LABEL_DISABLED": params.disable_video_quality_label,
            "SETTINGS_SECTIONS": params.settings.split(","),
            "TOOLBAR_BUTTONS": params.toolbar.split(","),
        }
    };

    const skittybop = new JitsiMeetExternalAPI(params.domain, options);
    skittybop.executeCommand("displayName", params.user ? params.user : '');
    skittybop.executeCommand("subject", params.subject);
    skittybop.executeCommand("localSubject", room);
    skittybop.executeCommand("avatarUrl", params.avatar);

    skittybop.on("videoConferenceLeft", (r) => {
        if (window.name === skittybopPopupTarget) {
            skittybopMarkCallTimestamp(room, false, true, true)
            window.close();
        } else {
            if (!openingPopOutWindow) {
                skittybopMarkCallTimestamp(room, false, true, false)
            }
            skittybopCloseActiveCallDialog();
        }
    });

    skittybop.on("videoConferenceJoined", (e) => {
        if (!isPopOutWindow) {
            skittybopMarkCallTimestamp(room, true, false, false)
        }
    });

    window.skittybop = skittybop;
}

function skittybopPositionDialogue() {
    jQuery('#skittybop-dialog-call').dialog({'position': {my: "center", at: "center", of: window}})
}

function skittybopAcceptCall(room) {
    skittybopMarkCallAs(room, args.status.accepted);
}

function skittybopRejectCall(room) {
    skittybopMarkCallAs(room, args.status.rejected);
}

function skittybopCancelCall(room) {
    skittybopMarkCallAs(room, args.status.canceled);
}

function skittybopFailCall(room) {
    skittybopMarkCallAs(room, args.status.failed);
}

function skittybopMarkCallAs(room, status) {
    const data = {
        'action': 'skittybop_change_call_status',
        '_wpnonce': jQuery("input#_wpnonce_skittybop_change_call_status").val(),
        'room': room,
        'status': status
    };

    jQuery.ajax({
        type: "POST",
        url: args.ajaxurl,
        data: data,
        success: function (data) {
            data = data ? JSON.parse(data) : data;
            const room = data?.room;
            if (room) {
                const jwt = data?.jwt;
                if (jwt) {
                    skittybopOpenActiveCallDialog(room, jwt);
                } else {
                    skittybopCancelCall(room);
                    skittybopOpenServiceUnavailableDialog();
                }

            } else if (status === args.status.accepted) {
                skittybopOpenAnsweredCallDialog();
            } else {
                skittybopClosePendingCallDialogs();
            }
        },
        error: function (data) {
            skittybopClosePendingCallDialogs();
        }
    });
}

function skittybopMarkCallTimestamp(room, start, end, beacon) {
    const data = {
        'action': 'skittybop_change_call_timestamp',
        '_wpnonce': jQuery("input#_wpnonce_skittybop_change_call_timestamp").val(),
        'room': room,
    };

    if (start) {
        data['start'] = true;
    }
    if (end) {
        data['end'] = true;
    }

    if (beacon) {
        let params = new URLSearchParams("");
        for(let key in data) {
            params.append(key, data[key]);
        }
        navigator.sendBeacon(args.ajaxurl, params);
    } else {
        jQuery.ajax({
            type: "POST",
            url: args.ajaxurl,
            data: data,
            success: function (e) {
                if (end) {
                    skittybopDestroyActiveClient();
                }
            },
            error: function (e) {
                console.error("failed to mark call's start / end timestamp", e)
                skittybopDestroyActiveClient();
            }
        });
    }

}

function popOutVideoCall(room, jwt) {
    openingPopOutWindow = true;

    let newWin = window.open("/skittybop/popout", skittybopPopupTarget, "width=500,height=500");
    newWin.focus();
    newWin.onload = (event) => {
        let jqueryCore = jQuery('#jquery-core-js').prop('outerHTML');
        let jqueryMigrate = jQuery('#jquery-migrate-js').prop('outerHTML');
        let jqueryUiCore = jQuery('#jquery-ui-core-js').prop('outerHTML');
        let skittybopCall = jQuery('#skittybop-call-js-js').prop('outerHTML');
        let skittybopJitsi = jQuery('#skittybop-jitsi-js-js').prop('outerHTML');
        let skittybopChangeTimestamp = jQuery('input#_wpnonce_skittybop_change_call_timestamp').prop('outerHTML');

        let content = `<script>let args = ` + JSON.stringify(args) + `</script>`;
        if(jqueryCore) {
            content += jqueryCore;
        } else {
            content += `<script src='/wp-admin/load-scripts.php?c=0&load%5Bchunk_0%5D=jquery-core,jquery-migrate,utils'></script>`;
        }
        if(jqueryMigrate) {
            content += jqueryMigrate;
        }
        if(jqueryUiCore) {
            content += jqueryUiCore;
        }
        if(skittybopCall) {
            content += skittybopCall;
        }
        if(skittybopJitsi) {
            content += skittybopJitsi;
        }
        if(skittybopChangeTimestamp) {
            content += skittybopChangeTimestamp;
        }
        content += `<title>` + args.name + `</title>`;
        content += `<div id='skittybopVideoCall'></div>`;
        content += `<script>isPopOutWindow = true; skittybopConnectToJitsiServer('` + room + `','` + jwt + `')</script>`;
        newWin.document.write(content);
    };
}
