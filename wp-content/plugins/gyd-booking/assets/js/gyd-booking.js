/* global GYDB */
( function () {
	'use strict';

	var i18n = ( window.GYDB && GYDB.i18n ) || {};

	function ajax( action, data ) {
		var body = new FormData();
		body.append( 'action', 'gydb_' + action );
		body.append( 'nonce', GYDB.nonce );
		Object.keys( data || {} ).forEach( function ( key ) {
			body.append( key, data[ key ] );
		} );
		return fetch( GYDB.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: body
		} ).then( function ( r ) {
			return r.json();
		} );
	}

	function initApp( app ) {
		var inModal = !! app.closest( '.gydb-modal-overlay' );

		var stages = {
			pick: app.querySelector( '.gydb-stage-pick' ),
			mentors: app.querySelector( '.gydb-stage-mentors' ),
			form: app.querySelector( '.gydb-stage-form' ),
			success: app.querySelector( '.gydb-stage-success' )
		};
		var loading = app.querySelector( '.gydb-loading' );
		var mentorTarget = app.querySelector( '.gydb-mentor-target' );
		var contextBox = app.querySelector( '.gydb-program-context' );
		var form = app.querySelector( '.gydb-form' );
		var slotsBox = app.querySelector( '.gydb-slots' );
		var dateInput = app.querySelector( '.gydb-date' );
		var timeInput = app.querySelector( '.gydb-time' );
		var programIdInput = app.querySelector( '.gydb-program-id' );
		var mentorIdInput = app.querySelector( '.gydb-mentor-id' );
		var errorBox = app.querySelector( '.gydb-form-error' );

		function showStage( name ) {
			Object.keys( stages ).forEach( function ( key ) {
				if ( stages[ key ] ) {
					stages[ key ].hidden = key !== name;
				}
			} );
			if ( app.dataset.booted && ! inModal ) {
				app.scrollIntoView( { behavior: 'smooth', block: 'start' } );
			} else if ( inModal ) {
				var box = app.closest( '.gydb-modal' );
				if ( box ) { box.scrollTop = 0; }
			}
			app.dataset.booted = '1';
		}

		function setLoading( on ) {
			if ( loading ) { loading.hidden = ! on; }
		}

		function showError( msg ) {
			if ( ! errorBox ) { return; }
			errorBox.textContent = msg;
			errorBox.hidden = ! msg;
		}

		function loadProgram( programId, autoMentorId ) {
			setLoading( true );
			showError( '' );
			ajax( 'get_program_view', { program_id: programId } ).then( function ( res ) {
				setLoading( false );
				if ( ! res || ! res.success ) {
					alert( ( res && res.data && res.data.message ) || i18n.genericError );
					return;
				}
				app.dataset.program = res.data.program_id;
				if ( contextBox ) { contextBox.innerHTML = res.data.context_html; }
				if ( mentorTarget ) { mentorTarget.innerHTML = res.data.mentors_html; }
				if ( programIdInput ) { programIdInput.value = res.data.program_id; }

				if ( autoMentorId ) {
					var card = mentorTarget && mentorTarget.querySelector( '.gydb-mentor-card[data-mentor-id="' + autoMentorId + '"]' );
					if ( card ) {
						pickMentor( card );
						return;
					}
				}
				showStage( 'mentors' );
			} ).catch( function () {
				setLoading( false );
				alert( i18n.genericError );
			} );
		}

		function pickMentor( card ) {
			if ( ! card ) { return; }
			var roleEl = card.querySelector( '.gydb-mentor-role' );
			var photoEl = card.querySelector( '.gydb-mentor-photo' );

			mentorIdInput.value = card.getAttribute( 'data-mentor-id' );

			var sMentor = app.querySelector( '.gydb-summary-mentor' );
			var sRole = app.querySelector( '.gydb-summary-role' );
			var sProg = app.querySelector( '.gydb-summary-prog' );
			var sPhoto = app.querySelector( '.gydb-summary-photo' );
			var progTitleEl = app.querySelector( '.gydb-sel-prog h4' );

			if ( sMentor ) { sMentor.textContent = card.getAttribute( 'data-mentor-name' ) || ''; }
			if ( sRole ) { sRole.textContent = roleEl ? roleEl.textContent : ''; }
			if ( sProg && progTitleEl ) { sProg.textContent = progTitleEl.textContent; }
			if ( sPhoto ) { sPhoto.style.backgroundImage = ( photoEl && photoEl.style.backgroundImage ) || ''; }

			if ( dateInput ) { dateInput.value = ''; }
			if ( timeInput ) { timeInput.value = ''; }
			if ( slotsBox ) {
				slotsBox.innerHTML = '<p class="gydb-slots-hint">' + ( i18n.selectDate || '' ) + '</p>';
			}
			showError( '' );
			showStage( 'form' );
		}

		function loadSlots() {
			var mentorId = mentorIdInput.value;
			var date = dateInput.value;
			if ( timeInput ) { timeInput.value = ''; }
			if ( ! mentorId || ! date ) { return; }
			slotsBox.innerHTML = '<p class="gydb-slots-hint">' + ( i18n.loading || '' ) + '</p>';
			ajax( 'get_slots', { mentor_id: mentorId, date: date } ).then( function ( res ) {
				if ( ! res || ! res.success ) {
					slotsBox.innerHTML = '<p class="gydb-slots-hint">' + ( ( res && res.data && res.data.message ) || i18n.genericError ) + '</p>';
					return;
				}
				var slots = res.data.slots || [];
				if ( ! slots.length ) {
					slotsBox.innerHTML = '<p class="gydb-slots-hint">' + ( i18n.noSlots || '' ) + '</p>';
					return;
				}
				slotsBox.innerHTML = '';
				slots.forEach( function ( slot ) {
					var btn = document.createElement( 'button' );
					btn.type = 'button';
					btn.className = 'gydb-slot';
					btn.setAttribute( 'data-time', slot.value );
					btn.textContent = slot.label;
					slotsBox.appendChild( btn );
				} );
			} ).catch( function () {
				slotsBox.innerHTML = '<p class="gydb-slots-hint">' + ( i18n.genericError || '' ) + '</p>';
			} );
		}

		function submit( e ) {
			e.preventDefault();
			showError( '' );

			var name = form.querySelector( '.gydb-name' );
			var email = form.querySelector( '.gydb-email' );
			var phone = form.querySelector( '.gydb-phone' );
			var message = form.querySelector( '.gydb-message' );

			[ name, email ].forEach( function ( el ) {
				el.classList.toggle( 'gydb-invalid', ! el.value.trim() );
			} );

			if ( ! name.value.trim() || ! email.value.trim() ) {
				showError( i18n.requiredField || 'Please complete the required fields.' );
				return;
			}
			if ( ! timeInput.value ) {
				showError( i18n.chooseSlot || 'Please choose a time slot.' );
				return;
			}

			var submitBtn = form.querySelector( '.gydb-submit' );
			var originalText = submitBtn.textContent;
			submitBtn.disabled = true;
			submitBtn.textContent = i18n.submitting || 'Submitting…';
			setLoading( true );

			ajax( 'submit_booking', {
				program_id: programIdInput.value,
				mentor_id: mentorIdInput.value,
				date: dateInput.value,
				time: timeInput.value,
				name: name.value,
				email: email.value,
				phone: phone ? phone.value : '',
				message: message ? message.value : ''
			} ).then( function ( res ) {
				setLoading( false );
				submitBtn.disabled = false;
				submitBtn.textContent = originalText;

				if ( ! res || ! res.success ) {
					showError( ( res && res.data && res.data.message ) || i18n.genericError );
					if ( dateInput.value ) { loadSlots(); }
					return;
				}

				var msgEl = app.querySelector( '.gydb-success-msg' );
				var refEl = app.querySelector( '.gydb-success-ref' );
				if ( msgEl && res.data.message ) { msgEl.textContent = res.data.message; }
				if ( refEl ) {
					refEl.textContent = ( res.data.summary ? res.data.summary + ' · ' : '' ) + 'Ref ' + res.data.reference;
				}
				form.reset();
				if ( timeInput ) { timeInput.value = ''; }
				showStage( 'success' );
			} ).catch( function () {
				setLoading( false );
				submitBtn.disabled = false;
				submitBtn.textContent = originalText;
				showError( i18n.genericError );
			} );
		}

		app.addEventListener( 'click', function ( e ) {
			var pick = e.target.closest( '.gydb-pick-program' );
			if ( pick && app.contains( pick ) ) {
				loadProgram( pick.getAttribute( 'data-program-id' ) );
				return;
			}
			var mentorBtn = e.target.closest( '.gydb-pick-mentor' );
			if ( mentorBtn ) {
				pickMentor( mentorBtn.closest( '.gydb-mentor-card' ) );
				return;
			}
			var slot = e.target.closest( '.gydb-slot' );
			if ( slot ) {
				Array.prototype.forEach.call( slotsBox.querySelectorAll( '.gydb-slot' ), function ( s ) {
					s.classList.remove( 'gydb-slot-active' );
				} );
				slot.classList.add( 'gydb-slot-active' );
				timeInput.value = slot.getAttribute( 'data-time' );
				showError( '' );
				return;
			}
			if ( e.target.closest( '.gydb-back-to-pick' ) ) {
				showStage( 'pick' );
				return;
			}
			if ( e.target.closest( '.gydb-back-to-mentors' ) ) {
				showStage( 'mentors' );
				return;
			}
			if ( e.target.closest( '.gydb-book-another' ) ) {
				showStage( 'mentors' );
				return;
			}
		} );

		app.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Enter' || e.key === ' ' ) {
				var pick = e.target.closest( '.gydb-pick-program' );
				if ( pick && app.contains( pick ) ) {
					e.preventDefault();
					loadProgram( pick.getAttribute( 'data-program-id' ) );
				}
			}
		} );

		if ( dateInput ) { dateInput.addEventListener( 'change', loadSlots ); }
		if ( form ) { form.addEventListener( 'submit', submit ); }

		// Public entry point used by the popup modal.
		app.gydbOpenFor = function ( programId, mentorId ) {
			showError( '' );
			if ( String( app.dataset.program ) === String( programId ) && mentorTarget && mentorTarget.children.length ) {
				// Same programme already loaded — reuse it.
				if ( mentorId ) {
					var existing = mentorTarget.querySelector( '.gydb-mentor-card[data-mentor-id="' + mentorId + '"]' );
					if ( existing ) { pickMentor( existing ); return; }
				}
				showStage( 'mentors' );
				return;
			}
			loadProgram( programId, mentorId );
		};

		// Initial stage (inline app only; the modal starts hidden/empty).
		if ( ! inModal ) {
			var hasProgram = app.getAttribute( 'data-program' );
			if ( hasProgram ) {
				showStage( 'mentors' );
				var wantMentor = app.getAttribute( 'data-mentor' );
				if ( wantMentor && wantMentor !== '0' ) {
					var card = app.querySelector( '.gydb-mentor-card[data-mentor-id="' + wantMentor + '"]' );
					if ( card ) { pickMentor( card ); }
				}
			} else if ( stages.pick ) {
				showStage( 'pick' );
			}
		}
	}

	/* ----- Popup modal open/close ----- */
	function openModal( programId, mentorId ) {
		var overlay = document.getElementById( 'gydb-modal' );
		if ( ! overlay ) { return; }
		var app = overlay.querySelector( '.gydb-app' );
		overlay.hidden = false;
		document.body.classList.add( 'gydb-modal-open' );
		if ( app && typeof app.gydbOpenFor === 'function' ) {
			app.gydbOpenFor( programId, mentorId || '' );
		}
		var closeBtn = overlay.querySelector( '.gydb-modal-close' );
		if ( closeBtn ) { closeBtn.focus(); }
	}

	function closeModal() {
		var overlay = document.getElementById( 'gydb-modal' );
		if ( ! overlay ) { return; }
		overlay.hidden = true;
		document.body.classList.remove( 'gydb-modal-open' );
	}

	document.addEventListener( 'click', function ( e ) {
		var trigger = e.target.closest( '.gydb-open-modal' );
		if ( trigger ) {
			e.preventDefault();
			openModal( trigger.getAttribute( 'data-program-id' ), trigger.getAttribute( 'data-mentor-id' ) );
			return;
		}
		if ( e.target.closest( '.gydb-modal-close' ) ) {
			closeModal();
			return;
		}
		// Click on the dark backdrop (outside the modal box) closes it.
		if ( e.target.classList && e.target.classList.contains( 'gydb-modal-overlay' ) ) {
			closeModal();
		}
	} );

	document.addEventListener( 'keydown', function ( e ) {
		if ( e.key === 'Escape' ) {
			var overlay = document.getElementById( 'gydb-modal' );
			if ( overlay && ! overlay.hidden ) { closeModal(); }
		}
	} );

	document.addEventListener( 'DOMContentLoaded', function () {
		Array.prototype.forEach.call( document.querySelectorAll( '.gydb-app' ), initApp );
	} );
}() );
