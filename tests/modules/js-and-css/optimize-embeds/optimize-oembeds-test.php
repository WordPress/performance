<?php
/**
 * Tests for embed optimizer.
 *
 * @package performance-lab
 * @group embed-optimizer
 */

class Embed_Optimizer_Helper_Tests extends WP_UnitTestCase {

	/**
	 * Test that the oEmbed HTML is filtered.
	 *
	 * @dataProvider test_oembed_optimize_html_data
	 */
	public function test_perflab_optimize_oembed_html( $html, $expected = null ) {
		if ( null === $expected ) {
			$expected = $html; // No change.
		}
		$this->assertEquals( $expected, perflab_optimize_oembed_html( $html ) );
	}

	/**
	 * Data provider for oEmbed HTML tests.
	 *
	 * @return array
	 */
	public function test_oembed_optimize_html_data() {
		return array(
			// A single iframe.
			array(
				'<iframe src="https://www.youtube.com/embed/123" width="560" height="315" frameborder="0"></iframe>',
				'<iframe loading="lazy" src="https://www.youtube.com/embed/123" width="560" height="315" frameborder="0"></iframe>',
			),

			// An iframe and a script.
			array(
				'<iframe src="https://www.youtube.com/embed/123" width="560" height="315" frameborder="0"></iframe><script src="https://www.youtube.com/embed/123"></script>',
				'<iframe loading="lazy" src="https://www.youtube.com/embed/123" width="560" height="315" frameborder="0"></iframe><script data-lazy-embed-src="https://www.youtube.com/embed/123" ></script>',
			),

			// An iframe that is already lazy loaded.
			array(
				'<iframe src="https://www.youtube.com/embed/123" width="560" height="315" frameborder="0" loading="lazy"></iframe>',
				null, // No change.
			),

			// An iframe and an inline script.
			array(
				'<iframe src="https://www.youtube.com/embed/123" width="560" height="315" frameborder="0"></iframe><script>console.log("Hello, World!");</script>',
				'<iframe loading="lazy" src="https://www.youtube.com/embed/123" width="560" height="315" frameborder="0"></iframe><script>console.log("Hello, World!");</script>',
			),

			// An instagram embed.
			array(
				'<blockquote class="instagram-media" data-instgrm-captioned data-instgrm-permalink="https://www.instagram.com/p/C3GCZZRJ54B/?utm_source=ig_embed&amp;utm_campaign=loading" data-instgrm-version="14" style=" background:#FFF; border:0; border-radius:3px; box-shadow:0 0 1px 0 rgba(0,0,0,0.5),0 1px 10px 0 rgba(0,0,0,0.15); margin: 1px; max-width:540px; min-width:326px; padding:0; width:99.375%; width:-webkit-calc(100% - 2px); width:calc(100% - 2px);"><div style="padding:16px;"> <a href="https://www.instagram.com/p/C3GCZZRJ54B/?utm_source=ig_embed&amp;utm_campaign=loading" style=" background:#FFFFFF; line-height:0; padding:0 0; text-align:center; text-decoration:none; width:100%;" target="_blank"> <div style=" display: flex; flex-direction: row; align-items: center;"> <div style="background-color: #F4F4F4; border-radius: 50%; flex-grow: 0; height: 40px; margin-right: 14px; width: 40px;"></div> <div style="display: flex; flex-direction: column; flex-grow: 1; justify-content: center;"> <div style=" background-color: #F4F4F4; border-radius: 4px; flex-grow: 0; height: 14px; margin-bottom: 6px; width: 100px;"></div> <div style=" background-color: #F4F4F4; border-radius: 4px; flex-grow: 0; height: 14px; width: 60px;"></div></div></div><div style="padding: 19% 0;"></div> <div style="display:block; height:50px; margin:0 auto 12px; width:50px;"><svg width="50px" height="50px" viewBox="0 0 60 60" version="1.1" xmlns="https://www.w3.org/2000/svg" xmlns:xlink="https://www.w3.org/1999/xlink"><g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd"><g transform="translate(-511.000000, -20.000000)" fill="#000000"><g><path d="..."></path></g></g></g></svg></div><div style="padding-top: 8px;"> <div style=" color:#3897f0; font-family:Arial,sans-serif; font-size:14px; font-style:normal; font-weight:550; line-height:18px;">View this post on Instagram</div></div><div style="padding: 12.5% 0;"></div> <div style="display: flex; flex-direction: row; margin-bottom: 14px; align-items: center;"><div> <div style="background-color: #F4F4F4; border-radius: 50%; height: 12.5px; width: 12.5px; transform: translateX(0px) translateY(7px);"></div> <div style="background-color: #F4F4F4; height: 12.5px; transform: rotate(-45deg) translateX(3px) translateY(1px); width: 12.5px; flex-grow: 0; margin-right: 14px; margin-left: 2px;"></div> <div style="background-color: #F4F4F4; border-radius: 50%; height: 12.5px; width: 12.5px; transform: translateX(9px) translateY(-18px);"></div></div><div style="margin-left: 8px;"> <div style=" background-color: #F4F4F4; border-radius: 50%; flex-grow: 0; height: 20px; width: 20px;"></div> <div style=" width: 0; height: 0; border-top: 2px solid transparent; border-left: 6px solid #f4f4f4; border-bottom: 2px solid transparent; transform: translateX(16px) translateY(-4px) rotate(30deg)"></div></div><div style="margin-left: auto;"> <div style=" width: 0px; border-top: 8px solid #F4F4F4; border-right: 8px solid transparent; transform: translateY(16px);"></div> <div style=" background-color: #F4F4F4; flex-grow: 0; height: 12px; width: 16px; transform: translateY(-4px);"></div> <div style=" width: 0; height: 0; border-top: 8px solid #F4F4F4; border-left: 8px solid transparent; transform: translateY(-4px) translateX(8px);"></div></div></div> <div style="display: flex; flex-direction: column; flex-grow: 1; justify-content: center; margin-bottom: 24px;"> <div style=" background-color: #F4F4F4; border-radius: 4px; flex-grow: 0; height: 14px; margin-bottom: 6px; width: 224px;"></div> <div style=" background-color: #F4F4F4; border-radius: 4px; flex-grow: 0; height: 14px; width: 144px;"></div></div></a><p style=" color:#c9c8cd; font-family:Arial,sans-serif; font-size:14px; line-height:17px; margin-bottom:0; margin-top:8px; overflow:hidden; padding:8px 0 7px; text-align:center; text-overflow:ellipsis; white-space:nowrap;"><a href="https://www.instagram.com/p/C3GCZZRJ54B/?utm_source=ig_embed&amp;utm_campaign=loading" style=" color:#c9c8cd; font-family:Arial,sans-serif; font-size:14px; font-style:normal; font-weight:normal; line-height:17px; text-decoration:none;" target="_blank">A post shared by Instagram (@instagram)</a></p></div></blockquote> <script async src="//www.instagram.com/embed.js"></script>',
				'<blockquote class="instagram-media" data-instgrm-captioned data-instgrm-permalink="https://www.instagram.com/p/C3GCZZRJ54B/?utm_source=ig_embed&amp;utm_campaign=loading" data-instgrm-version="14" style=" background:#FFF; border:0; border-radius:3px; box-shadow:0 0 1px 0 rgba(0,0,0,0.5),0 1px 10px 0 rgba(0,0,0,0.15); margin: 1px; max-width:540px; min-width:326px; padding:0; width:99.375%; width:-webkit-calc(100% - 2px); width:calc(100% - 2px);"><div style="padding:16px;"> <a href="https://www.instagram.com/p/C3GCZZRJ54B/?utm_source=ig_embed&amp;utm_campaign=loading" style=" background:#FFFFFF; line-height:0; padding:0 0; text-align:center; text-decoration:none; width:100%;" target="_blank"> <div style=" display: flex; flex-direction: row; align-items: center;"> <div style="background-color: #F4F4F4; border-radius: 50%; flex-grow: 0; height: 40px; margin-right: 14px; width: 40px;"></div> <div style="display: flex; flex-direction: column; flex-grow: 1; justify-content: center;"> <div style=" background-color: #F4F4F4; border-radius: 4px; flex-grow: 0; height: 14px; margin-bottom: 6px; width: 100px;"></div> <div style=" background-color: #F4F4F4; border-radius: 4px; flex-grow: 0; height: 14px; width: 60px;"></div></div></div><div style="padding: 19% 0;"></div> <div style="display:block; height:50px; margin:0 auto 12px; width:50px;"><svg width="50px" height="50px" viewBox="0 0 60 60" version="1.1" xmlns="https://www.w3.org/2000/svg" xmlns:xlink="https://www.w3.org/1999/xlink"><g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd"><g transform="translate(-511.000000, -20.000000)" fill="#000000"><g><path d="..."></path></g></g></g></svg></div><div style="padding-top: 8px;"> <div style=" color:#3897f0; font-family:Arial,sans-serif; font-size:14px; font-style:normal; font-weight:550; line-height:18px;">View this post on Instagram</div></div><div style="padding: 12.5% 0;"></div> <div style="display: flex; flex-direction: row; margin-bottom: 14px; align-items: center;"><div> <div style="background-color: #F4F4F4; border-radius: 50%; height: 12.5px; width: 12.5px; transform: translateX(0px) translateY(7px);"></div> <div style="background-color: #F4F4F4; height: 12.5px; transform: rotate(-45deg) translateX(3px) translateY(1px); width: 12.5px; flex-grow: 0; margin-right: 14px; margin-left: 2px;"></div> <div style="background-color: #F4F4F4; border-radius: 50%; height: 12.5px; width: 12.5px; transform: translateX(9px) translateY(-18px);"></div></div><div style="margin-left: 8px;"> <div style=" background-color: #F4F4F4; border-radius: 50%; flex-grow: 0; height: 20px; width: 20px;"></div> <div style=" width: 0; height: 0; border-top: 2px solid transparent; border-left: 6px solid #f4f4f4; border-bottom: 2px solid transparent; transform: translateX(16px) translateY(-4px) rotate(30deg)"></div></div><div style="margin-left: auto;"> <div style=" width: 0px; border-top: 8px solid #F4F4F4; border-right: 8px solid transparent; transform: translateY(16px);"></div> <div style=" background-color: #F4F4F4; flex-grow: 0; height: 12px; width: 16px; transform: translateY(-4px);"></div> <div style=" width: 0; height: 0; border-top: 8px solid #F4F4F4; border-left: 8px solid transparent; transform: translateY(-4px) translateX(8px);"></div></div></div> <div style="display: flex; flex-direction: column; flex-grow: 1; justify-content: center; margin-bottom: 24px;"> <div style=" background-color: #F4F4F4; border-radius: 4px; flex-grow: 0; height: 14px; margin-bottom: 6px; width: 224px;"></div> <div style=" background-color: #F4F4F4; border-radius: 4px; flex-grow: 0; height: 14px; width: 144px;"></div></div></a><p style=" color:#c9c8cd; font-family:Arial,sans-serif; font-size:14px; line-height:17px; margin-bottom:0; margin-top:8px; overflow:hidden; padding:8px 0 7px; text-align:center; text-overflow:ellipsis; white-space:nowrap;"><a href="https://www.instagram.com/p/C3GCZZRJ54B/?utm_source=ig_embed&amp;utm_campaign=loading" style=" color:#c9c8cd; font-family:Arial,sans-serif; font-size:14px; font-style:normal; font-weight:normal; line-height:17px; text-decoration:none;" target="_blank">A post shared by Instagram (@instagram)</a></p></div></blockquote> <script data-lazy-embed-src="//www.instagram.com/embed.js" async ></script>',
			),

			// Vimeo embed.
			array(
				'<iframe loading="lazy" title="Expedia - Plates (DC)" src="https://player.vimeo.com/video/806311047?dnt=1&amp;app_id=122963" width="500" height="281" frameborder="0" allow="autoplay; fullscreen; picture-in-picture"></iframe>',
				null, // No change.
			),

			// Twitter embed.
			array(
				'<blockquote class="twitter-tweet" data-width="500" data-dnt="true"><p lang="en" dir="ltr"Feedback <a href="https://t.co/anGk6gWkbc">https://t.co/anGk6gWkbc</a></p>&mdash; <a href="https://twitter.com/ChromiumDev/status/1636796541368139777?ref_src=twsrc%5Etfw">March 17, 2023</a></blockquote><script async src="https://platform.twitter.com/widgets.js" charset="utf-8"></script>',
				'<blockquote class="twitter-tweet" data-width="500" data-dnt="true"><p lang="en" dir="ltr"Feedback <a href="https://t.co/anGk6gWkbc">https://t.co/anGk6gWkbc</a></p>&mdash; <a href="https://twitter.com/ChromiumDev/status/1636796541368139777?ref_src=twsrc%5Etfw">March 17, 2023</a></blockquote><script data-lazy-embed-src="https://platform.twitter.com/widgets.js" async  charset="utf-8"></script>',
			),

			// Spotify embed.
			array(
				'<iframe title="Spotify Embed: Deep Focus" style="border-radius: 12px" width="100%" height="352" frameborder="0" allowfullscreen allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture" src="https://open.spotify.com/embed/playlist/37i9dQZF1DWZeKCadgRdKQ?utm_source=oembed"></iframe>',
				'<iframe loading="lazy" title="Spotify Embed: Deep Focus" style="border-radius: 12px" width="100%" height="352" frameborder="0" allowfullscreen allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture" src="https://open.spotify.com/embed/playlist/37i9dQZF1DWZeKCadgRdKQ?utm_source=oembed"></iframe>',
			),

			// Another post embed.
			array(
				'<blockquote class="wp-embedded-content" data-secret="vk2NMdp5d9"><a href="https://oembeds-test.instawp.xyz/hello-world/">Hello world!</a></blockquote><iframe class="wp-embedded-content" sandbox="allow-scripts" security="restricted" style="position: absolute; clip: rect(1px, 1px, 1px, 1px);" title="&#8220;Hello world!&#8221; &#8212; oEmbed Test Site" src="https://oembeds-test.instawp.xyz/hello-world/embed/#?secret=Ficznaefos#?secret=vk2NMdp5d9" data-secret="vk2NMdp5d9" width="500" height="282" frameborder="0" marginwidth="0" marginheight="0" scrolling="no"></iframe>',
				'<blockquote class="wp-embedded-content" data-secret="vk2NMdp5d9"><a href="https://oembeds-test.instawp.xyz/hello-world/">Hello world!</a></blockquote><iframe loading="lazy" class="wp-embedded-content" sandbox="allow-scripts" security="restricted" style="position: absolute; clip: rect(1px, 1px, 1px, 1px);" title="&#8220;Hello world!&#8221; &#8212; oEmbed Test Site" src="https://oembeds-test.instawp.xyz/hello-world/embed/#?secret=Ficznaefos#?secret=vk2NMdp5d9" data-secret="vk2NMdp5d9" width="500" height="282" frameborder="0" marginwidth="0" marginheight="0" scrolling="no"></iframe>',
			),

			// Reddit embed.
			array(
				'<blockquote class="reddit-embed-bq" style="height:316px" ><a href="https://www.reddit.com/r/AV1/comments/nsifir/hdr_avif_samples_hdr10_for_still_images/">HDR AVIF samples (HDR10 for still images)</a><br> by<a href="https://www.reddit.com/user/sh2be/">u/sh2be</a> in<a href="https://www.reddit.com/r/AV1/">AV1</a></blockquote><script async src="https://embed.reddit.com/widgets.js" charset="UTF-8"></script>',
				'<blockquote class="reddit-embed-bq" style="height:316px" ><a href="https://www.reddit.com/r/AV1/comments/nsifir/hdr_avif_samples_hdr10_for_still_images/">HDR AVIF samples (HDR10 for still images)</a><br> by<a href="https://www.reddit.com/user/sh2be/">u/sh2be</a> in<a href="https://www.reddit.com/r/AV1/">AV1</a></blockquote><script data-lazy-embed-src="https://embed.reddit.com/widgets.js" async  charset="UTF-8"></script>',
			),

			// TikTok embed.
			array(
				'<blockquote class="tiktok-embed" cite="https://www.tiktok.com/@mdmotivator/video/7203838800164916486" data-video-id="7203838800164916486" data-embed-from="oembed" style="max-width: 605px;min-width: 325px;" > <section> <a target="_blank" title="@mdmotivator" href="https://www.tiktok.com/@mdmotivator?refer=embed">@mdmotivator</a> <p>I tried giving $2000 iPhone away, BUT then this happened 🥺❤️ <a title="iphone" target="_blank" href="https://www.tiktok.com/tag/iphone?refer=embed">#iphone</a> <a title="mom" target="_blank" href="https://www.tiktok.com/tag/mom?refer=embed">#mom</a> <a title="lent" target="_blank" href="https://www.tiktok.com/tag/lent?refer=embed">#lent</a> </p> <a target="_blank" title="♬ original sound - Zachery Dereniowski" href="https://www.tiktok.com/music/original-sound-7203838814572628741?refer=embed">♬ original sound &#8211; Zachery Dereniowski</a> </section> </blockquote> <script async src="https://www.tiktok.com/embed.js"></script>',
				'<blockquote class="tiktok-embed" cite="https://www.tiktok.com/@mdmotivator/video/7203838800164916486" data-video-id="7203838800164916486" data-embed-from="oembed" style="max-width: 605px;min-width: 325px;" > <section> <a target="_blank" title="@mdmotivator" href="https://www.tiktok.com/@mdmotivator?refer=embed">@mdmotivator</a> <p>I tried giving $2000 iPhone away, BUT then this happened 🥺❤️ <a title="iphone" target="_blank" href="https://www.tiktok.com/tag/iphone?refer=embed">#iphone</a> <a title="mom" target="_blank" href="https://www.tiktok.com/tag/mom?refer=embed">#mom</a> <a title="lent" target="_blank" href="https://www.tiktok.com/tag/lent?refer=embed">#lent</a> </p> <a target="_blank" title="♬ original sound - Zachery Dereniowski" href="https://www.tiktok.com/music/original-sound-7203838814572628741?refer=embed">♬ original sound &#8211; Zachery Dereniowski</a> </section> </blockquote> <script data-lazy-embed-src="https://www.tiktok.com/embed.js" async ></script>',
			),
			// WordPress.tv embed.
			array(
				"<iframe title=\"VideoPress Video Player\" aria-label='VideoPress Video Player' width='500' height='281' src='https://video.wordpress.com/embed/Zv05OzFV?hd=1&amp;cover=1' frameborder='0' allowfullscreen allow='clipboard-write'></iframe><script src='https://v0.wordpress.com/js/next/videopress-iframe.js?m=1674852142'></script>",
				"<iframe loading=\"lazy\" title=\"VideoPress Video Player\" aria-label='VideoPress Video Player' width='500' height='281' src='https://video.wordpress.com/embed/Zv05OzFV?hd=1&amp;cover=1' frameborder='0' allowfullscreen allow='clipboard-write'></iframe><script data-lazy-embed-src=\"https://v0.wordpress.com/js/next/videopress-iframe.js?m=1674852142\" ></script>",
			),

			// Scribd embed.
			array(
				'<iframe title="ACLS Practice Test2" class="scribd_iframe_embed" src="https://www.scribd.com/embeds/135593338/content" data-aspect-ratio="0.7729220222793488" scrolling="no" id="135593338" width="500" height="750" frameborder="0"></iframe><script type="text/javascript">          (function() { var scribd = document.createElement("script"); scribd.type = "text/javascript"; scribd.async = true; scribd.src = "https://www.scribd.com/javascripts/embed_code/inject.js"; var s = document.getElementsByTagName("script")[0]; s.parentNode.insertBefore(scribd, s); })()        </script>',
				'<iframe loading="lazy" title="ACLS Practice Test2" class="scribd_iframe_embed" src="https://www.scribd.com/embeds/135593338/content" data-aspect-ratio="0.7729220222793488" scrolling="no" id="135593338" width="500" height="750" frameborder="0"></iframe><script type="text/javascript">          (function() { var scribd = document.createElement("script"); scribd.type = "text/javascript"; scribd.async = true; scribd.src = "https://www.scribd.com/javascripts/embed_code/inject.js"; var s = document.getElementsByTagName("script")[0]; s.parentNode.insertBefore(scribd, s); })()        </script>',
			),

			// Crowdsignal / Polldaddy embed.
			array(
				'<div class="pd-embed" data-settings="{&quot;type&quot;:&quot;iframe&quot;,&quot;auto&quot;:true,&quot;domain&quot;:&quot;napolidirect.survey.fm&quot;,&quot;id&quot;:&quot;customer-sample-survey-assessment&quot;,&quot;single_mode&quot;:false,&quot;placeholder&quot;:&quot;pd_1707508178&quot;}"></div><script type="text/javascript">(function(d,c,j){if(!document.getElementById(j)){var pd=d.createElement(c),s;pd.id=j;pd.src=\'https://app.crowdsignal.com/survey.js\';s=document.getElementsByTagName(c)[0];s.parentNode.insertBefore(pd,s);}}(document,\'script\',\'pd-embed\'));</script>',
				null, // No change.
			),

			// YouTube embed.
			array(
				'<iframe title="New in Chrome 111: View transitions API, CSS color level 4 and more!" width="500" height="281" src="https://www.youtube.com/embed/cscwgzz85Og?feature=oembed" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>',
				'<iframe loading="lazy" title="New in Chrome 111: View transitions API, CSS color level 4 and more!" width="500" height="281" src="https://www.youtube.com/embed/cscwgzz85Og?feature=oembed" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>',
			),
		);
	}
}
