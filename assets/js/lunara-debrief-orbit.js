/**
 * The Debrief constellation (3.2.95).
 *
 * Each slide is a server-rendered Debrief: the reviewed film at the centre and
 * its three pairings around it. This script measures the posters, lays a gold
 * line from the centre to each pairing and around the three of them, then runs
 * the sequence: the film arrives, the lines draw out, the pairings land, the
 * triangle closes, and after a hold the next Debrief takes over.
 *
 * - Without this script the first Debrief shows complete, with no lines.
 * - Reduced motion: lines drawn instantly, no autoplay; the tabs still work.
 * - Autoplay pauses off-screen, in a hidden tab, on hover or focus, and on
 *   the Pause button (which stays paused until pressed again).
 */
( function () {
	'use strict';

	var reduceQuery = window.matchMedia ? window.matchMedia( '(prefers-reduced-motion: reduce)' ) : null;

	function reducedMotion() {
		return !! ( reduceQuery && reduceQuery.matches );
	}

	function centreOf( el, origin ) {
		var r = el.getBoundingClientRect();
		return { x: r.left - origin.left + r.width / 2, y: r.top - origin.top + r.height / 2 };
	}

	/** Position every line in a slide from its endpoints' poster centres. */
	function layoutSlide( slide ) {
		var origin = slide.getBoundingClientRect();
		var points = {};
		slide.querySelectorAll( '[data-orbit-node]' ).forEach( function ( node ) {
			var poster = node.querySelector( '.lunara-debrief-orbit-poster' ) || node;
			points[ node.getAttribute( 'data-orbit-node' ) ] = centreOf( poster, origin );
		} );
		slide.querySelectorAll( '.lunara-debrief-orbit-line' ).forEach( function ( line ) {
			var a = points[ line.getAttribute( 'data-from' ) ];
			var b = points[ line.getAttribute( 'data-to' ) ];
			if ( ! a || ! b ) {
				line.style.display = 'none';
				return;
			}
			var dx = b.x - a.x;
			var dy = b.y - a.y;
			line.style.display = '';
			line.style.left = a.x + 'px';
			line.style.top = a.y + 'px';
			line.style.width = Math.sqrt( dx * dx + dy * dy ) + 'px';
			line.style.transform = 'rotate(' + Math.atan2( dy, dx ) + 'rad)';
		} );
		slide.classList.add( 'is-laid-out' );
	}

	function Orbit( root, scope ) {
		this.root = root;
		this.scope = scope;
		this.disposed = false;
		this.slides = Array.prototype.slice.call( root.querySelectorAll( '[data-orbit-slide]' ) );
		this.tabs = Array.prototype.slice.call( root.querySelectorAll( '[data-orbit-go]' ) );
		this.controls = root.querySelector( '[data-orbit-controls]' );
		this.toggle = root.querySelector( '[data-orbit-toggle]' );
		this.section = root.closest( 'section' );
		this.caption = this.section ? this.section.querySelector( '[data-orbit-caption]' ) : null;
		this.hold = parseInt( root.getAttribute( 'data-orbit-hold' ), 10 ) || 7200;
		this.index = 0;
		this.elapsed = 0;
		this.last = 0;
		this.frame = 0;
		this.userPaused = false;
		this.hovered = false;
		this.visible = false;
		this.started = false;
		var savedNodes = [ root, this.controls, this.toggle, this.caption ].concat( this.slides, this.tabs, Array.prototype.slice.call( root.querySelectorAll( '.lunara-debrief-orbit-line' ) ) ).filter( Boolean );
		this.initial = savedNodes.map( function ( node ) {
			var attributes = {};
			[ 'class', 'style', 'hidden', 'inert', 'aria-hidden', 'aria-current', 'aria-pressed', 'href' ].forEach( function ( name ) { attributes[ name ] = node.getAttribute( name ); } );
			return { node: node, attributes: attributes, inert: node.inert };
		} );
		this.initialCaption = this.caption ? this.caption.textContent : '';
		this.init();
	}

	Orbit.prototype.init = function () {
		var self = this;
		if ( ! this.slides.length ) {
			return;
		}

		this.root.classList.add( 'is-ready' );
		if ( ! reducedMotion() ) {
			this.root.classList.add( 'is-animated' );
			// Hold the first Debrief undrawn until it scrolls into view.
			this.slides[ 0 ].classList.remove( 'is-drawn' );
		}
		this.slides.forEach( function ( slide, i ) {
			if ( i !== 0 ) {
				slide.setAttribute( 'aria-hidden', 'true' );
				slide.inert = true;
			}
		} );
		layoutSlide( this.slides[ 0 ] );

		if ( this.controls && this.slides.length > 1 ) {
			this.controls.hidden = false;
			if ( reducedMotion() && this.toggle ) {
				this.toggle.hidden = true;
			}
		}

		this.tabs.forEach( function ( tab ) {
			self.scope.on( tab, 'click', function () {
				self.go( parseInt( tab.getAttribute( 'data-orbit-go' ), 10 ), true );
			} );
			self.scope.on( tab, 'keydown', function ( event ) {
				var step = 'ArrowRight' === event.key ? 1 : ( 'ArrowLeft' === event.key ? -1 : 0 );
				if ( step ) {
					event.preventDefault();
					var next = ( self.index + step + self.slides.length ) % self.slides.length;
					self.go( next, true );
					self.tabs[ next ].focus();
				}
			} );
		} );

		if ( this.toggle ) {
			this.scope.on( this.toggle, 'click', function () {
				self.userPaused = ! self.userPaused;
				self.toggle.setAttribute( 'aria-pressed', self.userPaused ? 'true' : 'false' );
				self.sync();
			} );
		}

		var stage = this.root.querySelector( '.lunara-debrief-orbit-stage' );
		this.scope.on( stage, 'mouseenter', function () { self.hovered = true; self.sync(); } );
		this.scope.on( stage, 'mouseleave', function () { self.hovered = false; self.sync(); } );
		this.scope.on( this.root, 'focusin', function () { self.hovered = true; self.sync(); } );
		this.scope.on( this.root, 'focusout', function ( event ) {
			if ( ! self.root.contains( event.relatedTarget ) ) {
				self.hovered = false;
				self.sync();
			}
		} );
		this.scope.on( document, 'visibilitychange', function () { self.sync(); } );

		if ( 'ResizeObserver' in window ) {
			this.scope.observe( new ResizeObserver( function () { if ( ! self.disposed ) layoutSlide( self.slides[ self.index ] ); } ) ).observe( stage );
		} else {
			this.scope.on( window, 'resize', function () { layoutSlide( self.slides[ self.index ] ); } );
		}
		// Posters arriving late move the centres.
		this.root.querySelectorAll( 'img' ).forEach( function ( img ) {
			if ( ! img.complete ) {
				self.scope.on( img, 'load', function () { layoutSlide( self.slides[ self.index ] ); }, { once: true } );
			}
		} );

		if ( 'IntersectionObserver' in window ) {
			this.scope.observe( new IntersectionObserver( function ( entries ) {
				if ( self.disposed || ! entries.length ) return;
				self.visible = entries[ 0 ].isIntersecting;
				if ( self.visible && ! self.started ) {
					self.started = true;
					self.draw( self.slides[ self.index ] );
				}
				self.sync();
			}, { threshold: 0.35 } ) ).observe( this.root );
		} else {
			this.visible = true;
			this.started = true;
			this.draw( this.slides[ 0 ] );
			this.sync();
		}
	};

	Orbit.prototype.draw = function ( slide ) {
		var self = this;
		if ( this.disposed ) return;
		layoutSlide( slide );
		// Two frames: commit the undrawn state, then let the transitions run.
		this.scope.frame( function () {
			self.scope.frame( function () {
				if ( ! self.disposed ) slide.classList.add( 'is-drawn' );
			} );
		} );
	};

	Orbit.prototype.go = function ( next, fromUser ) {
		if ( this.disposed || next === this.index || ! this.slides[ next ] ) {
			return;
		}
		var current = this.slides[ this.index ];
		var incoming = this.slides[ next ];

		current.classList.remove( 'is-active', 'is-drawn' );
		current.setAttribute( 'aria-hidden', 'true' );
		current.inert = true;

		incoming.classList.add( 'is-active' );
		incoming.removeAttribute( 'aria-hidden' );
		incoming.inert = false;
		this.draw( incoming );

		if ( this.tabs[ this.index ] ) {
			this.tabs[ this.index ].removeAttribute( 'aria-current' );
			this.tabs[ this.index ].style.removeProperty( '--orbit-progress' );
		}
		if ( this.tabs[ next ] ) {
			this.tabs[ next ].setAttribute( 'aria-current', 'true' );
		}
		if ( this.caption ) {
			this.caption.textContent = incoming.getAttribute( 'data-review-title' ) || '';
			this.caption.setAttribute( 'href', incoming.getAttribute( 'data-review-url' ) || '#' );
		}

		this.index = next;
		this.elapsed = 0;
		// A chosen Debrief gets a full hold before the sequence moves on.
		if ( fromUser ) {
			this.last = 0;
		}
	};

	Orbit.prototype.playing = function () {
		return ! this.disposed && this.slides.length > 1 && ! reducedMotion() && this.started && this.visible &&
			! this.userPaused && ! this.hovered && ! document.hidden;
	};

	Orbit.prototype.sync = function () {
		var self = this;
		this.root.classList.toggle( 'is-paused', ! this.playing() );
		if ( ! this.playing() ) {
			window.cancelAnimationFrame( this.frame );
			this.frame = 0;
			this.last = 0;
			return;
		}
		if ( this.frame ) {
			return;
		}
		var tick = function ( now ) {
			if ( ! self.playing() ) {
				self.frame = 0;
				return;
			}
			if ( self.last ) {
				self.elapsed += now - self.last;
			}
			self.last = now;
			var tab = self.tabs[ self.index ];
			if ( tab ) {
				tab.style.setProperty( '--orbit-progress', Math.min( 1, self.elapsed / self.hold ).toFixed( 4 ) );
			}
			if ( self.elapsed >= self.hold ) {
				self.go( ( self.index + 1 ) % self.slides.length, false );
			}
			self.frame = self.scope.frame( tick );
		};
		this.frame = this.scope.frame( tick );
	};

	Orbit.prototype.destroy = function () {
		this.disposed = true;
		window.cancelAnimationFrame( this.frame );
		this.initial.forEach( function ( saved ) {
			Object.keys( saved.attributes ).forEach( function ( name ) {
				if ( saved.attributes[ name ] === null ) saved.node.removeAttribute( name );
				else saved.node.setAttribute( name, saved.attributes[ name ] );
			} );
			if ( typeof saved.inert === 'boolean' ) saved.node.inert = saved.inert;
		} );
		if ( this.caption ) this.caption.textContent = this.initialCaption;
		delete this.root.lunaraOrbit;
	};

	function mount( pageRoot, scope ) {
		scope = scope || {
			on: function ( target, type, fn, options ) { if ( target ) target.addEventListener( type, fn, options ); },
			frame: window.requestAnimationFrame.bind( window ), observe: function ( observer ) { return observer; }, cleanup: function () {}
		};
		var roots = Array.prototype.slice.call( pageRoot.querySelectorAll( '[data-debrief-orbit]' ) );
		if ( pageRoot.matches && pageRoot.matches( '[data-debrief-orbit]' ) ) roots.unshift( pageRoot );
		roots.forEach( function ( root ) {
			if ( ! root.lunaraOrbit ) {
				root.lunaraOrbit = new Orbit( root, scope );
				var orbit = root.lunaraOrbit;
				scope.cleanup( function () { orbit.destroy(); } );
			}
		} );
	}

	function boot() { mount( document ); }
	if ( window.LunaraApp && window.LunaraApp.register ) {
		window.LunaraApp.register( 'debrief-orbit', mount );
	} else if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', boot, { once: true } );
	} else {
		boot();
	}
}() );
