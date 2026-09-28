(function ($) {
    "use strict";
	
	var $window = $(window); 
	var $body = $('body'); 

	/* Preloader Effect */
	$window.on('load', function(){
		$(".preloader").fadeOut(600);
	});

	/* Sticky Header */	
	if($('.active-sticky-header').length){
		$window.on('resize', function(){
			setHeaderHeight();
		});

		function setHeaderHeight(){
	 		$("header.active-sticky-header").css("height", $('header.active-sticky-header .header-sticky').outerHeight());
		}	
	
		$window.on("scroll", function() {
			var fromTop = $(window).scrollTop();
			setHeaderHeight();
			var headerHeight = $('header.active-sticky-header .header-sticky').outerHeight()
			$("header.active-sticky-header .header-sticky").toggleClass("hide", (fromTop > headerHeight + 100));
			$("header.active-sticky-header .header-sticky").toggleClass("active", (fromTop > 600));
		});
	}	
	
	/* Slick Menu JS */
	$('#menu').slicknav({
		label : '',
		prependTo : '.responsive-menu'
	});

	if($("a[href='#top']").length){
		$(document).on("click", "a[href='#top']", function() {
			$("html, body").animate({ scrollTop: 0 }, "slow");
			return false;
		});
	}

	/* Image Reveal Animation */
	if ($('.reveal').length) {
        gsap.registerPlugin(ScrollTrigger);
        let revealContainers = document.querySelectorAll(".reveal");
        revealContainers.forEach((container) => {
            let image = container.querySelector("img");
            let tl = gsap.timeline({
                scrollTrigger: {
                    trigger: container,
                    toggleActions: "play none none none"
                }
            });
            tl.set(container, {
                autoAlpha: 1
            });
            tl.from(container, 1, {
                xPercent: -100,
                ease: Power2.out
            });
            tl.from(image, 1, {
                xPercent: 100,
                scale: 1,
                delay: -1,
                ease: Power2.out
            });
        });
    }

	/* Text Effect Animation — Persian-safe (no character splitting) */
	function initHeadingAnimation() {
		if (typeof gsap === 'undefined') {
			return;
		}

		var prefersReduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
		if (prefersReduced) {
			$('.text-effect, .text-anime-style-1, .text-anime-style-2, .text-anime-style-3').css({ opacity: 1, visibility: 'visible', transform: 'none' });
			return;
		}

		gsap.registerPlugin(ScrollTrigger);
		if (typeof SplitText !== 'undefined') {
			gsap.registerPlugin(SplitText);
		}

		/* Whole-element reveal for .text-effect (no char split) */
		if ($('.text-effect').length) {
			$('.text-effect').each(function (index, el) {
				gsap.fromTo(el, {
					opacity: 0.35,
					y: 18
				}, {
					opacity: 1,
					y: 0,
					ease: 'power2.out',
					scrollTrigger: {
						trigger: el,
						start: 'top 92%',
						end: 'top 60%',
						scrub: 1,
						markers: false
					}
				});
			});
		}

		/* Style 1: animate by words only when SplitText exists; else whole element */
		if ($('.text-anime-style-1').length) {
			document.querySelectorAll('.text-anime-style-1').forEach(function (element) {
				if (typeof SplitText !== 'undefined') {
					var split = new SplitText(element, { type: 'words', wordsClass: 'split-word' });
					gsap.from(split.words, {
						duration: 1,
						delay: 0.5,
						x: 20,
						autoAlpha: 0,
						stagger: 0.05,
						scrollTrigger: { trigger: element, start: 'top 85%' }
					});
				} else {
					gsap.from(element, {
						duration: 0.85,
						delay: 0.2,
						y: 28,
						autoAlpha: 0,
						ease: 'power2.out',
						scrollTrigger: { trigger: element, start: 'top 85%' }
					});
				}
			});
		}

		/* Style 2 & 3: whole-element motion — NEVER split Persian into chars */
		document.querySelectorAll('.text-anime-style-2, .text-anime-style-3').forEach(function (element) {
			if (element.animation) {
				element.animation.progress(1).kill();
			}
			gsap.set(element, { perspective: 400 });
			element.animation = gsap.from(element, {
				opacity: 0,
				y: 30,
				x: 0,
				duration: 0.85,
				ease: 'power2.out',
				scrollTrigger: { trigger: element, start: 'top 90%' }
			});
		});
	}
	
	if (document.fonts && document.fonts.ready) {
        document.fonts.ready.then(() => {
            initHeadingAnimation();
        });
    } else {
        window.addEventListener("load", initHeadingAnimation);
    }
	
	/* Parallaxie js */
	var $parallaxie = $('.parallaxie');
	if($parallaxie.length && ($window.width() > 1024))
	{
		if ($window.width() > 768) {
			$parallaxie.parallaxie({
				speed: 0.55,
				offset: 0,
			});
		}
	}

	/* Zoom Gallery screenshot */
	if ($('.gallery-items').length) {
		$('.gallery-items').magnificPopup({
			delegate: 'a',
			type: 'image',
			closeOnContentClick: false,
			closeBtnInside: false,
			mainClass: 'mfp-with-zoom',
			image: {
				verticalFit: true,
			},
			gallery: {
				enabled: true
			},
			zoom: {
				enabled: true,
				duration: 300,
				opener: function(element) {
				  return element.find('img');
				}
			}
		});
	}

	/* Animated Wow Js */	
	new WOW().init();

	/* Popup Video */
	if ($('.popup-video').length) {
		$('.popup-video').magnificPopup({
			type: 'iframe',
			mainClass: 'mfp-fade',
			removalDelay: 160,
			preloader: false,
			fixedContentPos: true
		});
	}
	
	/* Before/after image comparison — square covers like Smilico */
	function initBeforeAfter() {
		var $compare = $('.transformation_image, .page-case-study-single .twentytwenty-container');
		if (!$compare.length || typeof $.fn.twentytwenty !== 'function') return;

		$compare.each(function () {
			var $el = $(this);
			if ($el.data('twentytwenty-init')) return;

			// Square frame before plugin measures image size
			var side = $el.width();
			if (side > 0) {
				$el.css({ width: '100%', height: side });
				$el.find('img').css({
					width: side,
					height: side,
					objectFit: 'cover',
					objectPosition: 'center center'
				});
			}

			$el.twentytwenty({ no_overlay: true });
			$el.data('twentytwenty-init', true);

			var syncSquare = function () {
				var w = $el.width();
				if (!w) return;
				$el.css('height', w);
				$el.find('img').css({
					width: w,
					height: w,
					maxWidth: 'none',
					objectFit: 'cover',
					objectPosition: 'center center'
				});
				$(window).trigger('resize.twentytwenty');
			};

			syncSquare();
			setTimeout(syncSquare, 50);
			setTimeout(syncSquare, 300);
			$window.on('resize.twentytwenty-square', syncSquare);
		});
	}
	if (document.readyState === 'complete') {
		initBeforeAfter();
	} else {
		$window.on('load', initBeforeAfter);
	}

	/* Service Item List Active Start */
	var $service_item_list = $('.service-item-list');
	if ($service_item_list.length) {
		var $service_item = $service_item_list.find('.service-item');

		if ($service_item.length) {
			$service_item.on({
				mouseenter: function () {
					if (!$(this).hasClass('active')) {
						$service_item.removeClass('active'); 
						$(this).addClass('active'); 
					}
				},
				mouseleave: function () {
					// Optional: Add logic for mouse leave if needed
				}
			});
		}
	}

})(jQuery);