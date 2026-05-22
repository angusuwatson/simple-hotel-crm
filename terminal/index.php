<?php
/**
 * LGF Bookings — Square Terminal Web App
 *
 * Full-screen ticket/bar ordering UI for Square Terminal.
 * Loaded by WordPress via template_redirect when ?simple_hotel_crm_terminal=1
 *
 * URL (after flush):
 *   https://lagrangefleurie.fr/terminal/
 *
 * 1. Log in with your WordPress credentials (once; session cookie persists).
 * 2. Point Square Dashboard → Terminal → Web Apps to the URL above.
 * 3. Terminal opens the app in full-screen webview.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ---- Require login ----
if ( ! is_user_logged_in() ) {
    wp_redirect( wp_login_url( ( is_ssl() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] ) );
    exit;
}

// ---- Nonce refresh endpoint ----
if ( isset( $_GET['refresh_nonce'] ) && '1' === $_GET['refresh_nonce'] ) {
    header( 'Content-Type: application/json' );
    echo json_encode( [ 'nonce' => wp_create_nonce( 'wp_rest' ) ] );
    exit;
}

// ---- Embed initial data ----
$rest_url    = rest_url( 'simple-hotel-crm/v1/' );
$nonce       = wp_create_nonce( 'wp_rest' );
$today       = current_time( 'Y-m-d' );
$catalog_raw = simple_hotel_crm_get_catalog_items();
$catalog     = $catalog_raw ?: [];

?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>LGF Orders</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;font-size:18px;color:#1a1a1a;background:#f5f5f7;overflow:hidden}
#app{display:flex;flex-direction:column;height:100vh}
/* Header */
.header{background:#1a1a2e;color:#fff;padding:12px 20px;display:flex;align-items:center;gap:16px;flex-shrink:0}
.header h1{font-size:22px;font-weight:700}
.header input[type=date]{font-size:18px;padding:8px 12px;border-radius:8px;border:none;background:#fff;color:#1a1a1a}
.header .user-name{margin-left:auto;font-size:14px;color:#aaa}
/* Main layout */
.main{display:flex;flex:1;overflow:hidden}
/* Left panel: bookings + catalog */
.left-panel{flex:1;display:flex;flex-direction:column;overflow:hidden;border-right:2px solid #ddd}
.bookings-row{display:flex;gap:10px;padding:12px;overflow-x:auto;flex-shrink:0;background:#fff;border-bottom:2px solid #e0e0e0;min-height:80px}
.booking-card{flex:0 0 auto;padding:10px 16px;border-radius:10px;border:2px solid #ddd;background:#fff;cursor:pointer;text-align:left;min-width:180px;transition:border-color .15s}
.booking-card:active,.booking-card-active{border-color:#1a1a2e;background:#f0f0ff}
.booking-card-active{border-width:3px;background:#e8e8ff}
.booking-name{font-weight:700;font-size:16px;white-space:nowrap}
.booking-meta{font-size:13px;color:#666;margin-top:2px}
.booking-room{font-size:12px;color:#999;margin-top:2px}
/* Catalog scrollable */
.catalog-scroll{flex:1;overflow-y:auto;padding:12px;background:#fff}
.cat-header{margin:12px 0 8px;font-size:17px;font-weight:700;color:#1a1a2e;text-transform:capitalize;cursor:pointer;user-select:none}
.cat-header:first-child{margin-top:0}
.cat-header .collapse-icon{display:inline-block;width:18px}
.item-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:8px;margin-bottom:4px}
.item-card{border:2px solid #e0e0e0;border-radius:10px;padding:14px 10px;text-align:center;cursor:pointer;background:#fafafa;transition:background .1s,border-color .1s}
.item-card:active{background:#1a1a2e;color:#fff;border-color:#1a1a2e}
.item-name{font-size:15px;font-weight:600;word-break:break-word}
.item-price{font-size:14px;color:#666;margin-top:4px}
.item-card:active .item-price{color:#ccc}
/* Right panel: ticket */
.ticket-panel{width:380px;flex-shrink:0;display:flex;flex-direction:column;background:#fff;overflow:hidden}
.ticket-header{padding:14px 16px;font-weight:700;font-size:16px;border-bottom:2px solid #e0e0e0;background:#fafafa;flex-shrink:0}
.ticket-header small{font-weight:400;font-size:13px;color:#666}
.ticket-items{flex:1;overflow-y:auto;padding:8px 12px;min-height:0}
.ticket-empty{color:#bbb;text-align:center;padding:40px 0;font-size:15px}
.ticket-item{display:flex;align-items:center;gap:8px;padding:8px 10px;border-radius:8px;cursor:pointer;transition:background .1s}
.ticket-item:active{background:#f0f0f0}
.ticket-item .ti-name{flex:1;font-size:15px;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.ticket-item .ti-qty{font-size:14px;color:#666;min-width:32px;text-align:center}
.ticket-item .ti-total{font-size:14px;font-weight:600;min-width:60px;text-align:right}
.ticket-item .ti-remove{background:none;border:none;font-size:20px;color:#c00;cursor:pointer;padding:0 4px;line-height:1}
.ticket-footer{padding:12px 16px;border-top:2px solid #e0e0e0;background:#fafafa;flex-shrink:0}
.ticket-total{font-size:20px;font-weight:700;margin-bottom:10px}
.ticket-actions{display:flex;gap:8px}
.ticket-actions button{flex:1;padding:14px;border-radius:10px;border:none;font-size:16px;font-weight:700;cursor:pointer;transition:background .15s}
.ticket-actions button:active{animation:pop .12s ease}
.btn-save{background:#1a1a2e;color:#fff}
.btn-save:active{background:#333366}
.btn-pay{background:#2e7d32;color:#fff}
.btn-pay:active{background:#1b5e20}

/* Error / indicator */
#ticket-error,#pay-error{background:#ffebee;color:#c62828;padding:10px 16px;border-radius:8px;margin:8px 12px;display:none;font-size:14px}
#pay-success{background:#e8f5e9;color:#2e7d32;padding:10px 16px;border-radius:8px;margin:8px 12px;display:none;font-size:14px}
#ticket-save-indicator{display:none;font-size:13px;color:#666}
/* Pay modal overlay */
.modal-overlay{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:1000;justify-content:center;align-items:center}
.modal-overlay.active{display:flex}
.modal-box{background:#fff;border-radius:16px;padding:24px;max-width:480px;width:90%;max-height:80vh;overflow-y:auto}
.modal-box h2{font-size:20px;margin-bottom:16px}
.pay-item{display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid #eee}
.pay-item input[type=checkbox]{width:20px;height:20px}
.pay-item label{flex:1;font-size:15px}
.pay-amount{font-weight:600;font-size:15px}
.pay-total{font-size:20px;font-weight:700;margin:14px 0;text-align:right}
.pay-actions{display:flex;gap:10px}
.pay-actions button{flex:1;padding:14px;border-radius:10px;border:none;font-size:16px;font-weight:700;cursor:pointer}
.pay-actions button:active{animation:pop .12s ease}
.btn-pay-confirm{background:#2e7d32;color:#fff}
.btn-pay-cancel{background:#e0e0e0;color:#333}
.btn-pay-confirm:disabled{opacity:.5}
/* Room selector */
.room-selector{padding:8px 12px;display:flex;align-items:center;gap:8px;font-size:14px;border-bottom:1px solid #e0e0e0;background:#fafafa}
.room-selector select{padding:6px 10px;border-radius:6px;border:1px solid #ccc;font-size:14px}
/* Fullscreen button */
.fs-btn{background:none;border:2px solid rgba(255,255,255,.4);border-radius:8px;color:#fff;font-size:22px;cursor:pointer;padding:4px 10px;line-height:1;transition:border-color .15s}
.fs-btn:active{border-color:#fff}
/* Animations */
@keyframes pop{50%{transform:scale(.92)}}
/* misc */
.text-muted{color:#aaa}
.text-sm{font-size:13px}
.w-full{width:100%}
.no-select{user-select:none}
</style>
</head>
<body>

<div id="app">
    <div class="header">
    <h1>LGF Orders</h1>
    <input type="date" id="ticket-date" value="<?php echo esc_attr( $today ); ?>">
    <button id="fs-toggle" class="fs-btn" title="Fullscreen">⛶</button>
    <span class="user-name"><?php echo esc_html( wp_get_current_user()->display_name ); ?></span>
  </div>

  <div class="main">
    <!-- Left: bookings + catalog -->
    <div class="left-panel">
      <div class="bookings-row" id="booking-cards"></div>
      <div class="catalog-scroll" id="item-grid"></div>
    </div>

    <!-- Right: ticket panel -->
    <div class="ticket-panel" id="ticket-panel">
      <div class="ticket-header" id="ticket-heading"><?php esc_html_e( 'Select a booking', 'simple-hotel-crm' ); ?></div>
      <div class="room-selector" id="room-selector" style="display:none;"></div>
      <div id="ticket-error"></div>
      <div id="pay-error"></div>
      <div id="pay-success"></div>
      <div class="ticket-items" id="ticket-items">
        <div class="ticket-empty"><?php esc_html_e( 'Select a booking to start.', 'simple-hotel-crm' ); ?></div>
      </div>
      <div class="ticket-footer">
        <div class="ticket-total" id="ticket-total"></div>
        <div class="ticket-actions">
          <button class="btn-save" id="save-ticket"><?php esc_html_e( 'Save', 'simple-hotel-crm' ); ?></button>

          <button class="btn-pay" id="pay-ticket"><?php esc_html_e( 'Pay', 'simple-hotel-crm' ); ?></button>
          <span id="ticket-save-indicator"><?php esc_html_e( 'Saving…', 'simple-hotel-crm' ); ?></span>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Pay Modal -->
<div class="modal-overlay" id="pay-modal">
  <div class="modal-box">
    <h2><?php esc_html_e( 'Confirm Payment', 'simple-hotel-crm' ); ?></h2>
    <div id="pay-items-list"></div>
    <div class="pay-total" id="pay-total"></div>
    <div id="pay-modal-error" style="background:#ffebee;color:#c62828;padding:10px 16px;border-radius:8px;margin:8px 0;display:none;font-size:14px"></div>
    <div style="margin:8px 0;">
      <label><input type="checkbox" id="pay-skip-receipt"> <?php esc_html_e( 'Skip receipt screen', 'simple-hotel-crm' ); ?></label>
    </div>
    <div class="pay-actions">
      <button class="btn-pay-confirm" id="pay-confirm"><?php esc_html_e( 'Pay with Terminal', 'simple-hotel-crm' ); ?></button>
      <button class="btn-pay-cancel" id="pay-cancel"><?php esc_html_e( 'Cancel', 'simple-hotel-crm' ); ?></button>
    </div>
  </div>
</div>

<script>
(function(){
var restUrl = <?php echo wp_json_encode( $rest_url ); ?>;
var wpNonce = <?php echo wp_json_encode( $nonce ); ?>;
var initialCatalog = <?php echo wp_json_encode( $catalog ); ?>;

var state = {
    date: document.getElementById('ticket-date').value,
    bookings: [],
    catalog: initialCatalog || [],
    roomsByBooking: {},
    activeBookingId: 0,
    activeRoomId: 0,
    bookingRooms: [],
    ticketItems: [],
    savedItems: [],
    roomNights: [],
    activeBooking: null,
    tempIdCounter: 0,
};

/* ---- Utilities ---- */
function el(tag, attrs, children){
    var e=document.createElement(tag);
    if(attrs) for(var k in attrs){
        if(k==='className') e.className=attrs[k];
        else if(k==='style'&&typeof attrs[k]==='object') for(var sk in attrs[k]) e.style[sk]=attrs[k][sk];
        else if(k.indexOf('on')===0&&typeof attrs[k]==='function') e.addEventListener(k.slice(2).toLowerCase(),attrs[k]);
        else e.setAttribute(k,attrs[k]);
    }
    if(children){
        if(typeof children==='string') e.textContent=children;
        else if(Array.isArray(children)) for(var i=0;i<children.length;i++){
            var c=children[i]; if(c==null) continue;
            if(typeof c==='string') e.appendChild(document.createTextNode(c));
            else e.appendChild(c);
        }
        else if(children instanceof Node) e.appendChild(children);
    }
    return e;
}
function qsa(s){return Array.from(document.querySelectorAll(s))}
function show(id){var e=document.getElementById(id);if(e)e.style.display=''}
function hide(id){var e=document.getElementById(id);if(e)e.style.display='none'}
function setText(id,t){var e=document.getElementById(id);if(e)e.textContent=t}
function setHTML(id,h){var e=document.getElementById(id);if(e)e.innerHTML=h}

function apiGet(path){
    return fetch(restUrl+path,{
        headers:{'X-WP-Nonce':wpNonce},
        credentials:'same-origin',
    }).then(function(r){
        if(!r.ok) return r.json().then(function(e){throw new Error(e.message||'API error')});
        return r.json();
    });
}
function apiPost(path,body){
    return fetch(restUrl+path,{
        method:'POST',
        headers:{'Content-Type':'application/json','X-WP-Nonce':wpNonce},
        credentials:'same-origin',
        body:JSON.stringify(body),
    }).then(function(r){
        if(!r.ok) return r.json().then(function(e){throw new Error(e.code?e.message:'API error')});
        return r.json();
    });
}

/* ---- Core ---- */
function fetchData(){
    var params=new URLSearchParams({date:state.date,_:Date.now()});
    if(state.activeBookingId>0) params.set('booking_id',state.activeBookingId);
    return apiGet('ticket-data?'+params.toString()).then(function(data){
        state.bookings=data.bookings||[];
        if(data.catalog) state.catalog=data.catalog;
        state.roomsByBooking=data.rooms_by_booking||{};
        if(state.activeBookingId>0){
            state.savedItems=data.items||[];
            state.bookingRooms=data.booking_rooms||[];
            state.roomNights=data.room_nights||[];
            state.ticketItems=(data.items||[]).map(function(item){
                return {id:item.id,name:item.item_name,qty:parseInt(item.quantity,10),price:parseFloat(item.unit_price)};
            });
        }else{
            state.savedItems=[];state.bookingRooms=[];state.roomNights=[];state.ticketItems=[];
        }
        render();
    }).catch(function(err){
        showError(err.message||'Failed to load data.');
    });
}

function render(){
    renderBookings();
    renderCatalog();
    renderTicket();
}

/* ---- Bookings ---- */
function renderBookings(){
    var container=document.getElementById('booking-cards');
    if(state.bookings.length===0){
        container.innerHTML='<div class="text-muted text-sm" style="padding:8px;"><?php echo esc_js( __( 'No bookings on this date.', 'simple-hotel-crm' ) ); ?></div>';
        hide('ticket-panel');
        return;
    }
    show('ticket-panel');
    var cards=state.bookings.map(function(b){
        var isActive=parseInt(b.id,10)===state.activeBookingId;
        var rooms=state.roomsByBooking[b.id]||[];
        var roomLabels=rooms.map(function(r){return r.room_code}).join(', ');
        var name=(b.first_name||'')+' '+(b.last_name||'');
        return el('div',{
            className:isActive?'booking-card-active':'booking-card',
            onClick:function(){selectBooking(parseInt(b.id,10));}
        },[
            el('div',{className:'booking-name'},[name.trim()||'#'+b.id]),
            el('div',{className:'booking-meta'},[
                '#'+b.id+' · '+b.check_in_date+' → '+b.check_out_date,
                (b.total_amount?' · '+parseFloat(b.total_amount).toFixed(2)+'€':''),
                (b.payment_status==='paid'||b.payment_status==='completed'?' ✓':''),
            ]),
            roomLabels?el('div',{className:'booking-room'},[roomLabels]):null,
        ]);
    });
    container.innerHTML='';
    cards.forEach(function(c){container.appendChild(c);});
}

/* ---- Catalog ---- */
function renderCatalog(){
    var grid=document.getElementById('item-grid');
    if(state.catalog.length===0){
        grid.innerHTML='<div class="text-muted text-sm" style="padding:20px;"><?php echo esc_js( __( 'No catalog items. Import CSV in Settings.', 'simple-hotel-crm' ) ); ?></div>';
        return;
    }
    var groups={};
    state.catalog.forEach(function(item){
        var cat=item.category||'other';
        if(!groups[cat]) groups[cat]=[];
        groups[cat].push(item);
    });
    var catOrder=['rooms','dinner','other'];
    var catLabels={'rooms':'Rooms','dinner':'Dinner','other':'Other'};
    grid.innerHTML='';
    catOrder.forEach(function(cat){
        var items=groups[cat];
        if(!items||items.length===0) return;
        var idx=catOrder.indexOf(cat);
        grid.appendChild(el('div',{
            className:'cat-header no-select',
            style:{marginTop:idx===0?'0':'12px'},
            onClick:function(e){
                var next=e.target.nextElementSibling;
                if(next&&next.classList.contains('item-grid')){
                    var hidden=next.style.display==='none';
                    next.style.display=hidden?'':'none';
                    var icon=e.target.querySelector('.collapse-icon');
                    if(icon) icon.textContent=hidden?'▼':'▶';
                }
            }
        },[
            el('span',{className:'collapse-icon'},['▼']),
            ' '+catLabels[cat]||cat,
            ' ('+items.length+')',
        ]));
        var sub=el('div',{className:'item-grid'});
        items.forEach(function(item){
            sub.appendChild(el('div',{
                className:'item-card',
                onClick:function(){addItem(item);}
            },[
                el('div',{className:'item-name'},[item.item_name]),
                el('div',{className:'item-price'},[parseFloat(item.unit_price).toFixed(2)+'€']),
            ]));
        });
        grid.appendChild(sub);
    });
}

/* ---- Ticket ---- */
function renderTicket(){
    if(!state.activeBookingId){
        hide('ticket-panel');
        return;
    }
    show('ticket-panel');
    var b=state.activeBooking;
    var name=b?((b.first_name||'')+' '+(b.last_name||'')).trim():'#'+state.activeBookingId;
    setText('ticket-heading',name+' — '+(state.bookingRooms.map(function(r){return r.room_code}).join(', ')||'No room'));

    var roomSel=document.getElementById('room-selector');
    if(state.bookingRooms.length>1){
        roomSel.style.display='flex';
        roomSel.innerHTML='<span>Room:</span><select id="room-select">'+
            '<option value="0">All rooms</option>'+
            state.bookingRooms.map(function(r){
                var sel=r.booking_room_id===state.activeRoomId?' selected':'';
                return '<option value="'+r.booking_room_id+'"'+sel+'>'+(r.room_name||r.room_code)+'</option>';
            }).join('')+
            '</select>';
        document.getElementById('room-select').addEventListener('change',function(e){
            selectRoom(parseInt(e.target.value,10));
        });
    }else{
        roomSel.style.display='none';
    }

    var itemsDiv=document.getElementById('ticket-items');
    if(state.ticketItems.length===0){
        itemsDiv.innerHTML='<div class="ticket-empty"><?php echo esc_js( __( 'Click catalog items to add.', 'simple-hotel-crm' ) ); ?></div>';
    }else{
        var rows=state.ticketItems.map(function(item,idx){
            return el('div',{className:'ticket-item',onClick:function(){incrementItem(idx);}},[
                el('span',{className:'ti-name'},[item.name]),
                el('span',{className:'ti-qty'},['×'+item.qty]),
                el('span',{className:'ti-total'},[(item.qty*item.price).toFixed(2)+'€']),
                el('button',{className:'ti-remove',type:'button',title:'Remove',
                    onClick:function(e){e.stopPropagation();removeItem(idx);}
                },['×']),
            ]);
        });
        itemsDiv.innerHTML='';
        rows.forEach(function(r){itemsDiv.appendChild(r);});
    }

    var total=state.ticketItems.reduce(function(s,item){return s+item.qty*item.price;},0);
    setText('ticket-total','Total: '+total.toFixed(2)+'€');
}

/* ---- Interactions ---- */
function selectBooking(bid){
    if(state.activeBookingId===bid) return;
    state.activeBookingId=bid;
    state.activeRoomId=0;
    state.activeBooking=null;
    state.ticketItems=[];
    for(var i=0;i<state.bookings.length;i++){
        if(parseInt(state.bookings[i].id,10)===bid){
            state.activeBooking=state.bookings[i];
            break;
        }
    }
    var rooms=state.roomsByBooking[bid]||[];
    if(rooms.length===1) state.activeRoomId=rooms[0].booking_room_id;
    fetchData();
}
function selectRoom(rid){
    state.activeRoomId=rid;
    state.ticketItems=[];
    fetchData();
}
function addItem(item){
    if(!state.activeBookingId){showError('<?php echo esc_js( __( 'Select a booking first.', 'simple-hotel-crm' ) ); ?>');return;}
    var name=item.item_name,price=parseFloat(item.unit_price);
    var found=-1;
    for(var i=0;i<state.ticketItems.length;i++){if(state.ticketItems[i].name===name){found=i;break;}}
    if(found>=0){state.ticketItems[found].qty+=1;}
    else{state.tempIdCounter-=1;state.ticketItems.push({id:state.tempIdCounter,name:name,qty:1,price:price});}
    renderTicket();
}
function incrementItem(idx){if(idx>=0&&idx<state.ticketItems.length){state.ticketItems[idx].qty+=1;renderTicket();}}
function removeItem(idx){if(idx>=0&&idx<state.ticketItems.length){state.ticketItems.splice(idx,1);renderTicket();}}
function showError(msg){
    var el=document.getElementById('ticket-error');
    if(el){el.textContent=msg;el.style.display='';setTimeout(function(){el.style.display='none';},4000);}
}
function hidePayError(){
    var e1=document.getElementById('pay-error');if(e1)e1.style.display='none';
    var e2=document.getElementById('pay-modal-error');if(e2)e2.style.display='none';
    var s=document.getElementById('pay-success');if(s)s.style.display='none';
}
function showPayError(msg){
    var el=document.getElementById('pay-modal-error')||document.getElementById('pay-error');
    if(el){el.textContent=msg;el.style.display='';setTimeout(function(){el.style.display='none';},5000);}
}

function saveTicket(){
    hide('ticket-error');
    show('ticket-save-indicator');
    return apiPost('ticket-save',{
        booking_id:state.activeBookingId,
        booking_room_id:state.activeRoomId||null,
        date:state.date,
        items:state.ticketItems.map(function(item){return {name:item.name,qty:item.qty,price:item.price};}),
    }).then(function(data){
        state.ticketItems=(data.items||[]).map(function(item){
            return {id:item.id,name:item.item_name,qty:parseInt(item.quantity,10),price:parseFloat(item.unit_price)};
        });
        state.savedItems=data.items||[];
        renderTicket();
        hide('ticket-save-indicator');
    }).catch(function(err){
        hide('ticket-save-indicator');
        showError(err.message||'Save failed');
        throw err;
    });
}

/* ---- Pay ---- */
function showPayModal(){
    if(!state.activeBookingId){showError('Select a booking first.');return;}
    hidePayError();
    saveTicket().then(function(){
        var list=document.getElementById('pay-items-list');

        var roomGroups={};
        state.roomNights.forEach(function(n){
            var key=n.booking_room_id;
            if(!roomGroups[key]) roomGroups[key]={roomName:n.room_name||n.room_code,roomTotal:0,taxTotal:0,nights:0};
            roomGroups[key].roomTotal+=parseFloat(n.room_rate_amount);
            roomGroups[key].taxTotal+=parseFloat(n.tourist_tax_amount);
            roomGroups[key].nights+=1;
        });

        var charges=[];
        Object.keys(roomGroups).forEach(function(key){
            var rg=roomGroups[key];
            if(rg.roomTotal>0) charges.push({id:'room-'+key,label:rg.roomName+' ('+rg.nights+' nuits)',amount:rg.roomTotal,type:'room'});
            if(rg.taxTotal>0) charges.push({id:'tax-'+key,label:rg.roomName+' — Taxe de séjour',amount:rg.taxTotal,type:'tax'});
        });
        (state.savedItems||[]).forEach(function(item){
            var total=parseFloat(item.quantity)*parseFloat(item.unit_price);
            charges.push({id:'item-'+item.id,label:item.item_name+' ×'+item.quantity,amount:total,type:'item'});
        });

        if(charges.length===0){showPayError('No charges to display.');return;}

        var rows=charges.map(function(c,idx){
            return el('div',{className:'pay-item'},[
                el('input',{type:'checkbox',id:'pay-chk-'+idx,checked:true,value:c.amount,'data-label':c.label}),
                el('label',{htmlFor:'pay-chk-'+idx},[c.label]),
                el('span',{className:'pay-amount'},[c.amount.toFixed(2)+'€']),
            ]);
        });
        list.innerHTML='';
        rows.forEach(function(r){list.appendChild(r);});
        updatePayTotal();
        document.getElementById('pay-modal').classList.add('active');
    }).catch(function(){});
}

function updatePayTotal(){
    var total=0;
    qsa('#pay-items-list input[type=checkbox]:checked').forEach(function(cb){total+=parseFloat(cb.value||0);});
    setText('pay-total','Total: '+total.toFixed(2)+'€');
}

function sendToTerminal(){
    hidePayError();
    var checkboxes=qsa('#pay-items-list input[type=checkbox]:checked');
    if(checkboxes.length===0){showPayError('Select at least one item to charge.');return;}
    var total=0;
    checkboxes.forEach(function(cb){total+=parseFloat(cb.value||0);});
    var skipReceipt=document.getElementById('pay-skip-receipt').checked;
    var btn=document.getElementById('pay-confirm');
    btn.disabled=true;
    btn.textContent='<?php echo esc_js( __( 'Sending to terminal…', 'simple-hotel-crm' ) ); ?>';

    apiPost('ticket-show-bill',{
        booking_id:state.activeBookingId,
        amount:total,
        skip_receipt:skipReceipt,
    }).then(function(data){
        btn.textContent='<?php echo esc_js( __( 'Waiting for terminal…', 'simple-hotel-crm' ) ); ?>';
        return pollCheckoutStatus(data.action_id);
    }).then(function(data){
        setHTML('pay-success','<?php echo esc_js( __( 'Payment sent to terminal!', 'simple-hotel-crm' ) ); ?> '+total.toFixed(2)+'€');
        show('pay-success');
        btn.textContent='✓ '+(total.toFixed(2))+'€';
        setTimeout(function(){closePayModal();fetchData();},3000);
    }).catch(function(err){
        showPayError(err.message||'Failed to send payment.');
        btn.disabled=false;
        btn.textContent='<?php echo esc_js( __( 'Retry', 'simple-hotel-crm' ) ); ?>';
    });
}

function pollCheckoutStatus(actionId){
    return new Promise(function(resolve,reject){
        var maxAttempts=150;
        var attempts=0;
        function poll(){
            apiGet('ticket-checkout-status/'+encodeURIComponent(actionId)).then(function(data){
                if(data.status==='completed'){
                    resolve(data);
                }else if(data.status==='canceled'||data.status==='failed'){
                    reject(new Error('Payment '+data.status+' on terminal.'));
                }else{
                    attempts++;
                    if(attempts>=maxAttempts){
                        reject(new Error('Timed out waiting for terminal.'));
                    }else{
                        setTimeout(poll,2000);
                    }
                }
            }).catch(function(err){
                reject(err);
            });
        }
        poll();
    });
}

function closePayModal(){
    document.getElementById('pay-modal').classList.remove('active');
    var btn=document.getElementById('pay-confirm');
    btn.disabled=false;
    btn.textContent='<?php echo esc_js( __( 'Pay with Terminal', 'simple-hotel-crm' ) ); ?>';
    hidePayError();
    hide('pay-success');
}



function escHtml(s){return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;')}

/* ---- Init ---- */
document.addEventListener('DOMContentLoaded',function(){
    var dateInput=document.getElementById('ticket-date');
    if(!dateInput) return;
    state.date=dateInput.value;

    dateInput.addEventListener('change',function(){
        state.date=this.value;
        state.activeBookingId=0;state.activeRoomId=0;state.activeBooking=null;
        state.ticketItems=[];state.savedItems=[];
        fetchData();
    });

    document.getElementById('save-ticket').addEventListener('click',function(){saveTicket().catch(function(){});});

    document.getElementById('pay-ticket').addEventListener('click',function(){showPayModal();});
    document.getElementById('pay-items-list').addEventListener('change',function(e){if(e.target.type==='checkbox') updatePayTotal();});
    document.getElementById('pay-confirm').addEventListener('click',function(){sendToTerminal();});
    document.getElementById('pay-cancel').addEventListener('click',function(){closePayModal();});

    // Fullscreen toggle
    var fsBtn=document.getElementById('fs-toggle');
    if(fsBtn){
        fsBtn.addEventListener('click',function(){
            var d=document.documentElement;
            if(!document.fullscreenElement&&!document.webkitFullscreenElement){
                if(d.requestFullscreen) d.requestFullscreen();
                else if(d.webkitRequestFullscreen) d.webkitRequestFullscreen();
            }else{
                if(document.exitFullscreen) document.exitFullscreen();
                else if(document.webkitExitFullscreen) document.webkitExitFullscreen();
            }
        });
        function fsChange(){
            fsBtn.textContent=(document.fullscreenElement||document.webkitFullscreenElement)?'✕':'⛶';
        }
        document.addEventListener('fullscreenchange',fsChange);
        document.addEventListener('webkitfullscreenchange',fsChange);
    }

    fetchData();
});

// Refresh nonce every 6h so long-running terminal sessions stay authenticated
setInterval(function(){
    fetch('?refresh_nonce=1',{credentials:'same-origin'})
    .then(function(r){return r.json();})
    .then(function(d){if(d.nonce) wpNonce=d.nonce;})
    .catch(function(){});
}, 6*60*60*1000);
})();
</script>
</body>
</html>
