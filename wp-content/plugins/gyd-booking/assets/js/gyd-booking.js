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
		var dateInput = app.querySelector( '#gydb-date' );
		var timeInput = app.querySelector( '#gydb-time' );
		var programIdInput = app.querySelector( '#gydb-program-id' );
		var mentorIdInput = app.querySelector( '#gydb-mentor-id' );
		var errorBox = app.querySelector( '.gydb-form-error' );

		function showStage( name ) {
			Object.keys( stages ).forEach( function ( key ) {
				if ( stages[ key ] ) {
					stages[ key ].hidden = key !== name;
				}
			} );
			// Scroll the app into view on stage change (but not on first paint).
			if ( app.dataset.booted ) {
				app.scrollIntoView( { behavior: 'smooth', block: 'start' } );
			}
			app.dataset.booted = '1';
		}

		function setLoading( on ) {
			if ( loading ) {
				loading.hidden = ! on;
			}
		}

		function showError( msg ) {
			if ( ! errorBox ) {
				return;
			}
			errorBox.textContent = msg;
			errorBox.hidden = ! msg;
		}

		/* ----- Programme selection (picker mode) ----- */
		function loadProgram( programId ) {
			setLoading( true );
			showError( '' );
			ajax( 'get_program_view', { program_id: programId } ).then( function ( res ) {
				setLoading( false );
				if ( ! res || ! res.success ) {
					alert( ( res && res.data && res.data.message ) || i18n.genericError );
					return;
				}
				app.dataset.program = res.data.program_id;
				if ( contextBox ) {
					contextBox.innerHTML = res.data.context_html;
				}
				if ( mentorTarget ) {
					mentorTarget.innerHTML = res.data.mentors_html;
				}
				if ( programIdInput ) {
					programIdInput.value = res.data.program_id;
				}
				showStage( 'mentors' );
			} ).catch( function () {
				setLoading( false );
				alert( i18n.genericError );
			} );
		}

		/* ----- Mentor selection ----- */
		function pickMentor( card ) {
			if ( ! card ) {
				return;
			}
			var mentorId = card.getAttribute( 'data-mentor-id' );
			var name = card.getAttribute( 'data-mentor-name' ) || '';
			var roleEl = card.querySelector( '.gydb-mentor-role' );
			var photoEl = card.querySelector( '.gydb-mentor-photo' );

			mentorIdInput.value = mentorId;

			var sMentor = app.querySelector( '.gydb-summary-mentor' );
			var sRole = app.querySelector( '.gydb-summary-role' );
			var sProg = app.querySelector( '.gydb-summary-prog' );
			var sPhoto = app.querySelector( '.gydb-summary-photo' );
			var progTitleEl = app.querySelector( '.gydb-sel-prog h4' );

			if ( sMentor ) { sMentor.textContent = name; }
			if ( sRole ) { sRole.textContent = roleEl ? roleEl.textContent : ''; }
			if ( sProg && progTitleEl ) { sProg.textContent = progTitleEl.textContent; }
			if ( sPhoto && photoEl ) { sPhoto.style.backgroundImage = photoEl.style.backgroundImage; }

			// Reset scheduling.
			if ( dateInput ) { dateInput.value = ''; }
			if ( timeInput ) { timeInput.value = ''; }
			if ( slotsBox ) {
				slotsBox.innerHTML = '<p class="gydb-slots-hint">' + ( i18n.selectDate || '' ) + '</p>';
			}
			showError( '' );
			showStage( 'form' );
		}

		/* ----- Slots ----- */
		function loadSlots() {
			var mentorId = mentorIdInput.value;
			var date = dateInput.value;
			if ( timeInput ) { timeInput.value = ''; }
			if ( ! mentorId || ! date ) {
				return;
			}
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

		/* ----- Submit ----- */
		function submit( e ) {
			e.preventDefault();
			showError( '' );

			var name = form.querySelector( '#gydb-name' );
			var email = form.querySelector( '#gydb-email' );
			var phone = form.querySelector( '#gydb-phone' );
			var message = form.querySelector( '#gydb-message' );

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
					// A taken slot — refresh availability.
					if ( dateInput.value ) {
						loadSlots();
					}
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

		/* ----- Event delegation ----- */
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
				// Return to the mentor list for the same programme.
				showStage( 'mentors' );
				return;
			}
		} );

		// Keyboard support for programme picker cards.
		app.addEventListener( 'keydown', function ( e ) {
			if ( ( e.key === 'Enter' || e.key === ' ' ) ) {
				var pick = e.target.closest( '.gydb-pick-program' );
				if ( pick && app.contains( pick ) ) {
					e.preventDefault();
					loadProgram( pick.getAttribute( 'data-program-id' ) );
				}
			}
		} );

		if ( dateInput ) {
			dateInput.addEventListener( 'change', loadSlots );
		}
		if ( form ) {
			form.addEventListener( 'submit', submit );
		}

		/* ----- Initial stage ----- */
		var hasProgram = app.getAttribute( 'data-program' );
		if ( hasProgram ) {
			showStage( 'mentors' );
			// Preselect a mentor if requested (?gyd_mentor=).
			var wantMentor = app.getAttribute( 'data-mentor' );
			if ( wantMentor && wantMentor !== '0' ) {
				var card = app.querySelector( '.gydb-mentor-card[data-mentor-id="' + wantMentor + '"]' );
				if ( card ) {
					pickMentor( card );
				}
			}
		} else {
			showStage( 'pick' );
		}
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		Array.prototype.forEach.call( document.querySelectorAll( '.gydb-app' ), initApp );
	} );
}() );
