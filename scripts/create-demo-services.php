<?php
/**
 * Create Demo Services Script
 *
 * Run with: wp eval-file wp-content/plugins/wp-sell-services/scripts/create-demo-services.php
 *
 * @package WPSellServices
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Demo service data with realistic content.
 */
$demo_services = array(
	// Graphics & Design.
	array(
		'title'       => 'I will design a stunning minimalist logo for your brand',
		'content'     => 'Get a professional, modern logo that captures your brand essence. I specialize in minimalist designs that are memorable, scalable, and perfect for all platforms. With 8+ years of experience, I\'ve helped 500+ businesses establish their visual identity.',
		'excerpt'     => 'Professional minimalist logo design with unlimited revisions and full ownership rights.',
		'category'    => 'Graphics & Design',
		'tags'        => array( 'logo design', 'minimalist', 'branding', 'business logo' ),
		'packages'    => array(
			array(
				'name'          => 'Basic',
				'description'   => '1 logo concept, PNG format, 3 revisions',
				'price'         => 25,
				'delivery_days' => 3,
				'revisions'     => 3,
			),
			array(
				'name'          => 'Standard',
				'description'   => '3 logo concepts, all formats, unlimited revisions',
				'price'         => 75,
				'delivery_days' => 5,
				'revisions'     => -1,
			),
			array(
				'name'          => 'Premium',
				'description'   => '5 concepts + brand guidelines + social kit',
				'price'         => 150,
				'delivery_days' => 7,
				'revisions'     => -1,
			),
		),
		'faqs'        => array(
			array(
				'question' => 'What file formats will I receive?',
				'answer'   => 'You\'ll receive AI, EPS, PDF, PNG, and JPG files suitable for print and web.',
			),
			array(
				'question' => 'Can I request revisions?',
				'answer'   => 'Absolutely! Revisions are included based on your package selection.',
			),
		),
		'requirements' => array(
			array(
				'question'    => 'What is your business name and industry?',
				'type'        => 'text',
				'required'    => true,
			),
			array(
				'question'    => 'Do you have any color preferences?',
				'type'        => 'textarea',
				'required'    => false,
			),
		),
		'addons'      => array(
			array(
				'title'             => 'Express 24-hour delivery',
				'description'       => 'Get your logo within 24 hours',
				'price'             => 30,
				'delivery_days_extra' => -2,
				'field_type'        => 'checkbox',
			),
			array(
				'title'             => 'Social media kit',
				'description'       => 'Optimized versions for all social platforms',
				'price'             => 25,
				'delivery_days_extra' => 1,
				'field_type'        => 'checkbox',
			),
		),
		'stats'       => array( 'views' => 2847, 'orders' => 156, 'rating' => 4.9, 'reviews' => 89 ),
		'featured'    => true,
	),
	array(
		'title'       => 'I will create eye-catching social media graphics and posts',
		'content'     => 'Stand out on social media with scroll-stopping graphics! I create custom designs for Instagram, Facebook, LinkedIn, Twitter, and Pinterest that match your brand and engage your audience.',
		'excerpt'     => 'Custom social media graphics that boost engagement and grow your following.',
		'category'    => 'Graphics & Design',
		'tags'        => array( 'social media', 'instagram', 'facebook', 'graphics' ),
		'packages'    => array(
			array(
				'name'          => 'Starter',
				'description'   => '5 custom posts for 1 platform',
				'price'         => 30,
				'delivery_days' => 2,
				'revisions'     => 2,
			),
			array(
				'name'          => 'Growth',
				'description'   => '15 posts for 2 platforms + stories',
				'price'         => 80,
				'delivery_days' => 4,
				'revisions'     => 3,
			),
			array(
				'name'          => 'Business',
				'description'   => '30 posts for all platforms + content calendar',
				'price'         => 180,
				'delivery_days' => 7,
				'revisions'     => -1,
			),
		),
		'faqs'        => array(
			array(
				'question' => 'Which platforms do you design for?',
				'answer'   => 'Instagram, Facebook, Twitter, LinkedIn, Pinterest, and TikTok.',
			),
		),
		'requirements' => array(
			array(
				'question'    => 'Share your brand guidelines or colors',
				'type'        => 'file',
				'required'    => false,
			),
		),
		'addons'      => array(),
		'stats'       => array( 'views' => 1523, 'orders' => 78, 'rating' => 4.8, 'reviews' => 45 ),
		'featured'    => false,
	),
	array(
		'title'       => 'I will design professional business cards and stationery',
		'content'     => 'Make a lasting first impression with professionally designed business cards and stationery. I create cohesive brand materials including letterheads, envelopes, and complete stationery sets.',
		'excerpt'     => 'Professional business card and stationery design for your brand identity.',
		'category'    => 'Graphics & Design',
		'tags'        => array( 'business cards', 'stationery', 'print design', 'corporate' ),
		'packages'    => array(
			array(
				'name'          => 'Basic',
				'description'   => 'Double-sided business card design',
				'price'         => 20,
				'delivery_days' => 2,
				'revisions'     => 2,
			),
			array(
				'name'          => 'Standard',
				'description'   => 'Business card + letterhead + envelope',
				'price'         => 50,
				'delivery_days' => 3,
				'revisions'     => 3,
			),
			array(
				'name'          => 'Premium',
				'description'   => 'Complete stationery set with brand guide',
				'price'         => 120,
				'delivery_days' => 5,
				'revisions'     => -1,
			),
		),
		'faqs'        => array(),
		'requirements' => array(
			array(
				'question'    => 'Provide your logo and contact information',
				'type'        => 'textarea',
				'required'    => true,
			),
		),
		'addons'      => array(
			array(
				'title'             => 'Print-ready files with bleed',
				'description'       => 'Files ready for professional printing',
				'price'             => 10,
				'delivery_days_extra' => 0,
				'field_type'        => 'checkbox',
			),
		),
		'stats'       => array( 'views' => 892, 'orders' => 34, 'rating' => 5.0, 'reviews' => 21 ),
		'featured'    => false,
	),

	// Digital Marketing.
	array(
		'title'       => 'I will create a complete SEO strategy to boost your rankings',
		'content'     => 'Dominate search results with a data-driven SEO strategy. I provide comprehensive keyword research, competitor analysis, on-page optimization, and a detailed action plan to improve your organic visibility.',
		'excerpt'     => 'Complete SEO audit and strategy to improve your Google rankings.',
		'category'    => 'Digital Marketing',
		'tags'        => array( 'SEO', 'keyword research', 'google ranking', 'organic traffic' ),
		'packages'    => array(
			array(
				'name'          => 'SEO Audit',
				'description'   => 'Technical SEO audit with recommendations',
				'price'         => 50,
				'delivery_days' => 3,
				'revisions'     => 1,
			),
			array(
				'name'          => 'Full Strategy',
				'description'   => 'Audit + keyword research + content plan',
				'price'         => 150,
				'delivery_days' => 5,
				'revisions'     => 2,
			),
			array(
				'name'          => 'Enterprise',
				'description'   => 'Complete SEO roadmap + monthly support',
				'price'         => 400,
				'delivery_days' => 10,
				'revisions'     => -1,
			),
		),
		'faqs'        => array(
			array(
				'question' => 'How long until I see results?',
				'answer'   => 'SEO is a long-term strategy. You\'ll typically see improvements in 3-6 months.',
			),
			array(
				'question' => 'Do you guarantee first page rankings?',
				'answer'   => 'No one can guarantee rankings, but my strategies consistently improve visibility.',
			),
		),
		'requirements' => array(
			array(
				'question'    => 'What is your website URL?',
				'type'        => 'text',
				'required'    => true,
			),
			array(
				'question'    => 'Who are your main competitors?',
				'type'        => 'textarea',
				'required'    => false,
			),
		),
		'addons'      => array(
			array(
				'title'             => 'Competitor backlink analysis',
				'description'       => 'Deep dive into competitor link profiles',
				'price'             => 75,
				'delivery_days_extra' => 2,
				'field_type'        => 'checkbox',
			),
		),
		'stats'       => array( 'views' => 3421, 'orders' => 198, 'rating' => 4.9, 'reviews' => 112 ),
		'featured'    => true,
	),
	array(
		'title'       => 'I will manage your Google Ads campaigns for maximum ROI',
		'content'     => 'Get more leads and sales with expertly managed Google Ads campaigns. I handle everything from keyword research to ad copy, bidding optimization, and conversion tracking.',
		'excerpt'     => 'Expert Google Ads management to maximize your advertising ROI.',
		'category'    => 'Digital Marketing',
		'tags'        => array( 'google ads', 'PPC', 'paid advertising', 'lead generation' ),
		'packages'    => array(
			array(
				'name'          => 'Setup',
				'description'   => 'Campaign setup and initial optimization',
				'price'         => 100,
				'delivery_days' => 5,
				'revisions'     => 2,
			),
			array(
				'name'          => 'Monthly',
				'description'   => 'Full month campaign management',
				'price'         => 300,
				'delivery_days' => 30,
				'revisions'     => -1,
			),
			array(
				'name'          => 'Quarterly',
				'description'   => '3 months management with advanced tracking',
				'price'         => 800,
				'delivery_days' => 90,
				'revisions'     => -1,
			),
		),
		'faqs'        => array(
			array(
				'question' => 'Is the ad spend included?',
				'answer'   => 'No, this covers management only. Ad spend is paid directly to Google.',
			),
		),
		'requirements' => array(
			array(
				'question'    => 'What is your monthly ad budget?',
				'type'        => 'text',
				'required'    => true,
			),
		),
		'addons'      => array(
			array(
				'title'             => 'Landing page design',
				'description'       => 'Custom landing page for your campaign',
				'price'             => 150,
				'delivery_days_extra' => 3,
				'field_type'        => 'checkbox',
			),
		),
		'stats'       => array( 'views' => 1876, 'orders' => 67, 'rating' => 4.7, 'reviews' => 38 ),
		'featured'    => false,
	),
	array(
		'title'       => 'I will grow your Instagram following organically',
		'content'     => 'Build a genuine Instagram community with proven organic growth strategies. No bots, no fake followers - just real engagement and sustainable growth through content strategy and community building.',
		'excerpt'     => 'Organic Instagram growth through strategic content and engagement.',
		'category'    => 'Digital Marketing',
		'tags'        => array( 'instagram', 'social media growth', 'followers', 'engagement' ),
		'packages'    => array(
			array(
				'name'          => 'Starter',
				'description'   => 'Growth strategy + 2 weeks engagement',
				'price'         => 75,
				'delivery_days' => 14,
				'revisions'     => 1,
			),
			array(
				'name'          => 'Growth',
				'description'   => 'Strategy + 1 month engagement + analytics',
				'price'         => 200,
				'delivery_days' => 30,
				'revisions'     => 2,
			),
			array(
				'name'          => 'Influencer',
				'description'   => '3 months growth + content calendar + stories',
				'price'         => 500,
				'delivery_days' => 90,
				'revisions'     => -1,
			),
		),
		'faqs'        => array(
			array(
				'question' => 'How many followers can I expect?',
				'answer'   => 'Results vary, but clients typically see 500-2000 new followers monthly.',
			),
		),
		'requirements' => array(
			array(
				'question'    => 'What is your Instagram handle?',
				'type'        => 'text',
				'required'    => true,
			),
		),
		'addons'      => array(),
		'stats'       => array( 'views' => 2134, 'orders' => 89, 'rating' => 4.6, 'reviews' => 52 ),
		'featured'    => false,
	),

	// Programming & Tech.
	array(
		'title'       => 'I will build a modern responsive WordPress website',
		'content'     => 'Get a professional, fast-loading WordPress website that looks amazing on all devices. I use the latest themes and plugins to create secure, SEO-friendly sites that convert visitors into customers.',
		'excerpt'     => 'Custom WordPress website design and development with responsive design.',
		'category'    => 'Programming & Tech',
		'tags'        => array( 'wordpress', 'web development', 'responsive design', 'website' ),
		'packages'    => array(
			array(
				'name'          => 'Landing Page',
				'description'   => 'Single page website with contact form',
				'price'         => 150,
				'delivery_days' => 5,
				'revisions'     => 2,
			),
			array(
				'name'          => 'Business Site',
				'description'   => '5-page website with blog and SEO setup',
				'price'         => 400,
				'delivery_days' => 10,
				'revisions'     => 3,
			),
			array(
				'name'          => 'E-commerce',
				'description'   => 'Full WooCommerce store with payment setup',
				'price'         => 800,
				'delivery_days' => 14,
				'revisions'     => -1,
			),
		),
		'faqs'        => array(
			array(
				'question' => 'Do I need to have hosting?',
				'answer'   => 'Yes, you\'ll need hosting and a domain. I can recommend reliable options.',
			),
			array(
				'question' => 'Will I be able to update the site myself?',
				'answer'   => 'Absolutely! I\'ll provide training and documentation.',
			),
		),
		'requirements' => array(
			array(
				'question'    => 'What is the purpose of your website?',
				'type'        => 'textarea',
				'required'    => true,
			),
			array(
				'question'    => 'Share any reference websites you like',
				'type'        => 'textarea',
				'required'    => false,
			),
		),
		'addons'      => array(
			array(
				'title'             => 'Premium theme license',
				'description'       => 'Includes a premium theme ($59 value)',
				'price'             => 40,
				'delivery_days_extra' => 0,
				'field_type'        => 'checkbox',
			),
			array(
				'title'             => 'Speed optimization',
				'description'       => 'Advanced caching and performance tuning',
				'price'             => 50,
				'delivery_days_extra' => 1,
				'field_type'        => 'checkbox',
			),
		),
		'stats'       => array( 'views' => 4521, 'orders' => 234, 'rating' => 4.9, 'reviews' => 156 ),
		'featured'    => true,
	),
	array(
		'title'       => 'I will develop a custom React or Next.js web application',
		'content'     => 'Build powerful, scalable web applications with React or Next.js. From dashboards to SaaS platforms, I deliver clean, maintainable code with modern best practices.',
		'excerpt'     => 'Custom React/Next.js development for modern web applications.',
		'category'    => 'Programming & Tech',
		'tags'        => array( 'react', 'nextjs', 'javascript', 'web app' ),
		'packages'    => array(
			array(
				'name'          => 'Component',
				'description'   => 'Single React component or feature',
				'price'         => 100,
				'delivery_days' => 3,
				'revisions'     => 2,
			),
			array(
				'name'          => 'Module',
				'description'   => 'Complete feature module with API integration',
				'price'         => 350,
				'delivery_days' => 7,
				'revisions'     => 3,
			),
			array(
				'name'          => 'Full App',
				'description'   => 'Complete web application from scratch',
				'price'         => 1500,
				'delivery_days' => 21,
				'revisions'     => -1,
			),
		),
		'faqs'        => array(
			array(
				'question' => 'Do you provide the source code?',
				'answer'   => 'Yes, you receive full ownership of all source code.',
			),
		),
		'requirements' => array(
			array(
				'question'    => 'Describe your project requirements',
				'type'        => 'textarea',
				'required'    => true,
			),
		),
		'addons'      => array(
			array(
				'title'             => 'TypeScript implementation',
				'description'       => 'Full TypeScript with type safety',
				'price'             => 100,
				'delivery_days_extra' => 2,
				'field_type'        => 'checkbox',
			),
		),
		'stats'       => array( 'views' => 1654, 'orders' => 45, 'rating' => 5.0, 'reviews' => 28 ),
		'featured'    => false,
	),
	array(
		'title'       => 'I will fix bugs and issues in your WordPress website',
		'content'     => 'Having WordPress problems? I\'ll diagnose and fix any issues - from white screen of death to plugin conflicts, slow loading, security issues, and more. Fast turnaround guaranteed.',
		'excerpt'     => 'Expert WordPress troubleshooting and bug fixing service.',
		'category'    => 'Programming & Tech',
		'tags'        => array( 'wordpress', 'bug fix', 'troubleshooting', 'maintenance' ),
		'packages'    => array(
			array(
				'name'          => 'Quick Fix',
				'description'   => 'Fix 1 specific issue or bug',
				'price'         => 30,
				'delivery_days' => 1,
				'revisions'     => 1,
			),
			array(
				'name'          => 'Full Debug',
				'description'   => 'Comprehensive site audit and fix up to 5 issues',
				'price'         => 80,
				'delivery_days' => 2,
				'revisions'     => 2,
			),
			array(
				'name'          => 'Site Rescue',
				'description'   => 'Complete site recovery and optimization',
				'price'         => 200,
				'delivery_days' => 3,
				'revisions'     => -1,
			),
		),
		'faqs'        => array(
			array(
				'question' => 'What if you can\'t fix the issue?',
				'answer'   => 'Full refund if I cannot resolve your WordPress issue.',
			),
		),
		'requirements' => array(
			array(
				'question'    => 'Describe the issue you\'re experiencing',
				'type'        => 'textarea',
				'required'    => true,
			),
			array(
				'question'    => 'Provide wp-admin access',
				'type'        => 'text',
				'required'    => true,
			),
		),
		'addons'      => array(
			array(
				'title'             => 'Priority support',
				'description'       => 'Start working within 1 hour',
				'price'             => 25,
				'delivery_days_extra' => 0,
				'field_type'        => 'checkbox',
			),
		),
		'stats'       => array( 'views' => 3287, 'orders' => 312, 'rating' => 4.8, 'reviews' => 198 ),
		'featured'    => true,
	),
	array(
		'title'       => 'I will create a custom REST API for your application',
		'content'     => 'Need a robust backend API? I design and develop RESTful APIs using Node.js, Python, or PHP with proper authentication, documentation, and best practices.',
		'excerpt'     => 'Custom REST API development with documentation and testing.',
		'category'    => 'Programming & Tech',
		'tags'        => array( 'API', 'backend', 'nodejs', 'REST' ),
		'packages'    => array(
			array(
				'name'          => 'Basic',
				'description'   => 'Simple CRUD API with 5 endpoints',
				'price'         => 200,
				'delivery_days' => 5,
				'revisions'     => 2,
			),
			array(
				'name'          => 'Standard',
				'description'   => 'Full API with auth and 15 endpoints',
				'price'         => 500,
				'delivery_days' => 10,
				'revisions'     => 3,
			),
			array(
				'name'          => 'Enterprise',
				'description'   => 'Complete API with testing and CI/CD',
				'price'         => 1200,
				'delivery_days' => 21,
				'revisions'     => -1,
			),
		),
		'faqs'        => array(),
		'requirements' => array(
			array(
				'question'    => 'What data will the API handle?',
				'type'        => 'textarea',
				'required'    => true,
			),
		),
		'addons'      => array(
			array(
				'title'             => 'Swagger documentation',
				'description'       => 'Interactive API documentation',
				'price'             => 75,
				'delivery_days_extra' => 1,
				'field_type'        => 'checkbox',
			),
		),
		'stats'       => array( 'views' => 987, 'orders' => 23, 'rating' => 4.9, 'reviews' => 15 ),
		'featured'    => false,
	),

	// Video & Animation.
	array(
		'title'       => 'I will edit your YouTube videos professionally',
		'content'     => 'Make your YouTube videos stand out with professional editing. I add engaging intros, transitions, graphics, color grading, and sound design to keep viewers watching.',
		'excerpt'     => 'Professional YouTube video editing with effects and optimization.',
		'category'    => 'Video & Animation',
		'tags'        => array( 'video editing', 'youtube', 'content creator', 'post production' ),
		'packages'    => array(
			array(
				'name'          => 'Basic',
				'description'   => 'Basic cuts and transitions, up to 10 min',
				'price'         => 50,
				'delivery_days' => 3,
				'revisions'     => 1,
			),
			array(
				'name'          => 'Standard',
				'description'   => 'Full editing with graphics, up to 20 min',
				'price'         => 120,
				'delivery_days' => 5,
				'revisions'     => 2,
			),
			array(
				'name'          => 'Premium',
				'description'   => 'Cinematic editing + thumbnails, up to 30 min',
				'price'         => 250,
				'delivery_days' => 7,
				'revisions'     => -1,
			),
		),
		'faqs'        => array(
			array(
				'question' => 'What footage format do you accept?',
				'answer'   => 'I work with all common formats including MP4, MOV, AVI, and RAW files.',
			),
		),
		'requirements' => array(
			array(
				'question'    => 'Share your raw footage via cloud link',
				'type'        => 'text',
				'required'    => true,
			),
		),
		'addons'      => array(
			array(
				'title'             => 'Custom thumbnail design',
				'description'       => 'Eye-catching thumbnail for your video',
				'price'             => 15,
				'delivery_days_extra' => 0,
				'field_type'        => 'checkbox',
			),
			array(
				'title'             => 'Rush delivery',
				'description'       => 'Get your video in 24 hours',
				'price'             => 50,
				'delivery_days_extra' => -2,
				'field_type'        => 'checkbox',
			),
		),
		'stats'       => array( 'views' => 2876, 'orders' => 178, 'rating' => 4.8, 'reviews' => 95 ),
		'featured'    => true,
	),
	array(
		'title'       => 'I will create a 2D animated explainer video',
		'content'     => 'Explain your product or service with an engaging animated video. I create custom 2D animations that simplify complex ideas and capture attention.',
		'excerpt'     => 'Custom 2D animated explainer videos for your business.',
		'category'    => 'Video & Animation',
		'tags'        => array( 'animation', 'explainer video', '2D animation', 'motion graphics' ),
		'packages'    => array(
			array(
				'name'          => '30 Seconds',
				'description'   => '30-second animated video with voiceover',
				'price'         => 150,
				'delivery_days' => 7,
				'revisions'     => 2,
			),
			array(
				'name'          => '60 Seconds',
				'description'   => '1-minute video with custom illustrations',
				'price'         => 280,
				'delivery_days' => 10,
				'revisions'     => 3,
			),
			array(
				'name'          => '90 Seconds',
				'description'   => '90-second premium animation with music',
				'price'         => 450,
				'delivery_days' => 14,
				'revisions'     => -1,
			),
		),
		'faqs'        => array(
			array(
				'question' => 'Is voiceover included?',
				'answer'   => 'Yes, professional voiceover is included in all packages.',
			),
		),
		'requirements' => array(
			array(
				'question'    => 'Provide your script or key points',
				'type'        => 'textarea',
				'required'    => true,
			),
		),
		'addons'      => array(
			array(
				'title'             => 'Script writing',
				'description'       => 'Professional scriptwriting service',
				'price'             => 50,
				'delivery_days_extra' => 2,
				'field_type'        => 'checkbox',
			),
		),
		'stats'       => array( 'views' => 1543, 'orders' => 67, 'rating' => 4.9, 'reviews' => 41 ),
		'featured'    => false,
	),
	array(
		'title'       => 'I will create stunning motion graphics for your brand',
		'content'     => 'Elevate your brand with custom motion graphics. From logo animations to promotional videos, I create eye-catching visuals that make your content memorable.',
		'excerpt'     => 'Custom motion graphics and logo animations for brands.',
		'category'    => 'Video & Animation',
		'tags'        => array( 'motion graphics', 'logo animation', 'after effects', 'promo video' ),
		'packages'    => array(
			array(
				'name'          => 'Logo Reveal',
				'description'   => 'Animated logo intro/outro',
				'price'         => 75,
				'delivery_days' => 3,
				'revisions'     => 2,
			),
			array(
				'name'          => 'Promo Video',
				'description'   => '15-30 second promotional animation',
				'price'         => 200,
				'delivery_days' => 5,
				'revisions'     => 3,
			),
			array(
				'name'          => 'Full Package',
				'description'   => 'Logo reveal + promo + social versions',
				'price'         => 400,
				'delivery_days' => 7,
				'revisions'     => -1,
			),
		),
		'faqs'        => array(),
		'requirements' => array(
			array(
				'question'    => 'Share your logo file (AI/EPS preferred)',
				'type'        => 'file',
				'required'    => true,
			),
		),
		'addons'      => array(),
		'stats'       => array( 'views' => 876, 'orders' => 34, 'rating' => 5.0, 'reviews' => 22 ),
		'featured'    => false,
	),

	// Writing & Translation.
	array(
		'title'       => 'I will write SEO-optimized blog articles that rank',
		'content'     => 'Get high-quality, researched blog content that drives organic traffic. I write engaging articles optimized for search engines with proper headings, keywords, and internal linking.',
		'excerpt'     => 'SEO-optimized blog articles and content writing service.',
		'category'    => 'Writing & Translation',
		'tags'        => array( 'blog writing', 'SEO content', 'copywriting', 'articles' ),
		'packages'    => array(
			array(
				'name'          => '500 Words',
				'description'   => '500-word SEO article with 1 keyword',
				'price'         => 25,
				'delivery_days' => 2,
				'revisions'     => 1,
			),
			array(
				'name'          => '1000 Words',
				'description'   => '1000-word article with images and meta',
				'price'         => 50,
				'delivery_days' => 3,
				'revisions'     => 2,
			),
			array(
				'name'          => '2000 Words',
				'description'   => 'Long-form content with research and sources',
				'price'         => 100,
				'delivery_days' => 5,
				'revisions'     => -1,
			),
		),
		'faqs'        => array(
			array(
				'question' => 'Do you use AI to write?',
				'answer'   => 'All content is 100% human-written and original.',
			),
			array(
				'question' => 'Can you match my brand voice?',
				'answer'   => 'Yes! Share examples and I\'ll match your style perfectly.',
			),
		),
		'requirements' => array(
			array(
				'question'    => 'What topic should I write about?',
				'type'        => 'textarea',
				'required'    => true,
			),
			array(
				'question'    => 'Target keywords (if any)',
				'type'        => 'text',
				'required'    => false,
			),
		),
		'addons'      => array(
			array(
				'title'             => 'Royalty-free images',
				'description'       => '3 relevant images included',
				'price'             => 10,
				'delivery_days_extra' => 0,
				'field_type'        => 'checkbox',
			),
		),
		'stats'       => array( 'views' => 3654, 'orders' => 287, 'rating' => 4.9, 'reviews' => 176 ),
		'featured'    => true,
	),
	array(
		'title'       => 'I will translate your content from English to Spanish',
		'content'     => 'Professional English to Spanish translation by a native speaker. I deliver accurate, culturally-adapted translations for websites, documents, marketing materials, and more.',
		'excerpt'     => 'Native Spanish translation services for all content types.',
		'category'    => 'Writing & Translation',
		'tags'        => array( 'translation', 'spanish', 'localization', 'language' ),
		'packages'    => array(
			array(
				'name'          => 'Basic',
				'description'   => 'Up to 500 words translation',
				'price'         => 20,
				'delivery_days' => 1,
				'revisions'     => 1,
			),
			array(
				'name'          => 'Standard',
				'description'   => 'Up to 2000 words with proofreading',
				'price'         => 60,
				'delivery_days' => 3,
				'revisions'     => 2,
			),
			array(
				'name'          => 'Premium',
				'description'   => 'Up to 5000 words + localization',
				'price'         => 120,
				'delivery_days' => 5,
				'revisions'     => -1,
			),
		),
		'faqs'        => array(
			array(
				'question' => 'Which Spanish dialect do you use?',
				'answer'   => 'I can adapt to Latin American or Castilian Spanish based on your needs.',
			),
		),
		'requirements' => array(
			array(
				'question'    => 'Upload your document or paste the text',
				'type'        => 'file',
				'required'    => true,
			),
		),
		'addons'      => array(
			array(
				'title'             => 'Certified translation',
				'description'       => 'Official certification for legal documents',
				'price'             => 30,
				'delivery_days_extra' => 1,
				'field_type'        => 'checkbox',
			),
		),
		'stats'       => array( 'views' => 1234, 'orders' => 89, 'rating' => 5.0, 'reviews' => 56 ),
		'featured'    => false,
	),
	array(
		'title'       => 'I will proofread and edit your document professionally',
		'content'     => 'Polish your writing to perfection. I provide thorough proofreading and editing for grammar, spelling, punctuation, clarity, and flow. Academic and business documents welcome.',
		'excerpt'     => 'Professional proofreading and editing for all document types.',
		'category'    => 'Writing & Translation',
		'tags'        => array( 'proofreading', 'editing', 'grammar', 'academic' ),
		'packages'    => array(
			array(
				'name'          => 'Proofreading',
				'description'   => 'Grammar and spelling check, up to 2000 words',
				'price'         => 20,
				'delivery_days' => 1,
				'revisions'     => 1,
			),
			array(
				'name'          => 'Line Editing',
				'description'   => 'Style and flow improvements, up to 5000 words',
				'price'         => 50,
				'delivery_days' => 2,
				'revisions'     => 2,
			),
			array(
				'name'          => 'Deep Edit',
				'description'   => 'Comprehensive editing with feedback, up to 10000 words',
				'price'         => 100,
				'delivery_days' => 4,
				'revisions'     => -1,
			),
		),
		'faqs'        => array(),
		'requirements' => array(
			array(
				'question'    => 'Upload your document',
				'type'        => 'file',
				'required'    => true,
			),
			array(
				'question'    => 'What type of document is this?',
				'type'        => 'select',
				'choices'     => 'Academic,Business,Creative,Technical',
				'required'    => true,
			),
		),
		'addons'      => array(),
		'stats'       => array( 'views' => 876, 'orders' => 67, 'rating' => 4.8, 'reviews' => 43 ),
		'featured'    => false,
	),

	// Business.
	array(
		'title'       => 'I will be your dedicated virtual assistant',
		'content'     => 'Free up your time with a reliable virtual assistant. I handle email management, calendar scheduling, data entry, research, travel booking, and administrative tasks efficiently.',
		'excerpt'     => 'Professional virtual assistant for all your admin needs.',
		'category'    => 'Business',
		'tags'        => array( 'virtual assistant', 'admin support', 'data entry', 'scheduling' ),
		'packages'    => array(
			array(
				'name'          => '5 Hours',
				'description'   => '5 hours of VA support',
				'price'         => 50,
				'delivery_days' => 7,
				'revisions'     => 0,
			),
			array(
				'name'          => '10 Hours',
				'description'   => '10 hours with priority response',
				'price'         => 90,
				'delivery_days' => 7,
				'revisions'     => 0,
			),
			array(
				'name'          => '20 Hours',
				'description'   => '20 hours + weekly reports',
				'price'         => 160,
				'delivery_days' => 14,
				'revisions'     => 0,
			),
		),
		'faqs'        => array(
			array(
				'question' => 'What tools do you use?',
				'answer'   => 'I\'m proficient in Google Workspace, Microsoft Office, Asana, Trello, Slack, and more.',
			),
		),
		'requirements' => array(
			array(
				'question'    => 'What tasks do you need help with?',
				'type'        => 'textarea',
				'required'    => true,
			),
		),
		'addons'      => array(
			array(
				'title'             => 'Weekend availability',
				'description'       => 'Work on Saturday and Sunday',
				'price'             => 20,
				'delivery_days_extra' => 0,
				'field_type'        => 'checkbox',
			),
		),
		'stats'       => array( 'views' => 2134, 'orders' => 156, 'rating' => 4.9, 'reviews' => 98 ),
		'featured'    => true,
	),
	array(
		'title'       => 'I will create a professional business plan',
		'content'     => 'Get a comprehensive business plan for investors, banks, or internal use. I create detailed plans with market analysis, financial projections, and actionable strategies.',
		'excerpt'     => 'Professional business plan writing for startups and SMEs.',
		'category'    => 'Business',
		'tags'        => array( 'business plan', 'startup', 'investor', 'financial projections' ),
		'packages'    => array(
			array(
				'name'          => 'Lean Plan',
				'description'   => '10-page lean business plan',
				'price'         => 150,
				'delivery_days' => 5,
				'revisions'     => 2,
			),
			array(
				'name'          => 'Standard',
				'description'   => '25-page detailed plan with financials',
				'price'         => 350,
				'delivery_days' => 10,
				'revisions'     => 3,
			),
			array(
				'name'          => 'Investor Ready',
				'description'   => '40+ page plan with pitch deck',
				'price'         => 700,
				'delivery_days' => 14,
				'revisions'     => -1,
			),
		),
		'faqs'        => array(
			array(
				'question' => 'What information do you need from me?',
				'answer'   => 'Basic details about your business idea, target market, and goals.',
			),
		),
		'requirements' => array(
			array(
				'question'    => 'Describe your business idea',
				'type'        => 'textarea',
				'required'    => true,
			),
		),
		'addons'      => array(
			array(
				'title'             => 'Pitch deck design',
				'description'       => '10-slide investor presentation',
				'price'             => 100,
				'delivery_days_extra' => 2,
				'field_type'        => 'checkbox',
			),
		),
		'stats'       => array( 'views' => 1456, 'orders' => 45, 'rating' => 4.7, 'reviews' => 28 ),
		'featured'    => false,
	),
);

/**
 * Sample review texts used when seeding demo reviews.
 *
 * @var string[]
 */
$demo_review_texts = array(
	'Excellent work! Delivered exactly what I needed and the quality was outstanding.',
	'Very professional and communicative. Would definitely hire again.',
	'Great service, fast delivery, and the results exceeded my expectations.',
	'Top-notch quality. The attention to detail was impressive.',
	'Super fast turnaround and the work was exactly as described.',
	'Amazing quality! I have used this service multiple times and am always satisfied.',
	'Delivered on time with great results. Highly recommended!',
	'Fantastic experience from start to finish. The work is incredible.',
	'Outstanding quality and very responsive. Could not be happier.',
	'Very pleased with the final result. Professional and talented.',
	'Exactly what I was looking for! Will order again for sure.',
	'Great communication and delivered a top-quality product.',
	'Absolutely love the work done. Very creative and professional.',
	'The quality is top-tier and the communication was excellent throughout.',
	'Really impressed with the level of detail and professionalism.',
	'Wonderful experience. The results are beyond what I expected.',
	'Very skilled and talented. The work speaks for itself.',
	'Highly recommend this service. Delivered everything promised and more.',
	'Perfect execution. I am very happy with the results.',
	'Great value for the price. The quality far exceeded my expectations.',
);

/**
 * Generate a distribution of individual rating values that average to the target.
 *
 * Works for averages in the 4.0–5.0 range by distributing leftover points as
 * 4-star ratings (all non-5-star reviews are treated as 4-star).
 *
 * @param float $target_avg Target average (e.g. 4.9).
 * @param int   $count      Total number of reviews.
 * @return int[] Array of individual rating integers.
 */
function wpss_generate_rating_distribution( float $target_avg, int $count ): array {
	if ( $count <= 0 ) {
		return array();
	}

	$total_needed = (int) round( $target_avg * $count );
	$fives        = $total_needed - ( 4 * $count ); // derive from: 5x + 4(n-x) = total.
	$fives        = max( 0, min( $count, $fives ) );
	$fours        = $count - $fives;

	$ratings = array_merge(
		array_fill( 0, $fives, 5 ),
		array_fill( 0, $fours, 4 )
	);

	shuffle( $ratings );
	return $ratings;
}

/**
 * Get category ID by name.
 *
 * @param string $name Category name.
 * @return int Term ID.
 */
function wpss_get_category_id( $name ) {
	$term = get_term_by( 'name', $name, 'wpss_service_category' );
	return $term ? $term->term_id : 0;
}

/**
 * Create a demo service.
 *
 * @param array $data Service data.
 * @return int|WP_Error Post ID on success.
 */
function wpss_create_demo_service( $data ) {
	// Create post.
	$post_id = wp_insert_post(
		array(
			'post_type'    => 'wpss_service',
			'post_title'   => $data['title'],
			'post_content' => $data['content'],
			'post_excerpt' => $data['excerpt'],
			'post_status'  => 'publish',
			'post_author'  => 1,
		)
	);

	if ( is_wp_error( $post_id ) ) {
		return $post_id;
	}

	// Assign category.
	$cat_id = wpss_get_category_id( $data['category'] );
	if ( $cat_id ) {
		wp_set_object_terms( $post_id, $cat_id, 'wpss_service_category' );
	}

	// Assign tags.
	if ( ! empty( $data['tags'] ) ) {
		wp_set_object_terms( $post_id, $data['tags'], 'wpss_service_tag' );
	}

	// Save packages.
	if ( ! empty( $data['packages'] ) ) {
		update_post_meta( $post_id, '_wpss_packages', $data['packages'] );

		// Compute derived values from packages.
		$prices        = wp_list_pluck( $data['packages'], 'price' );
		$delivery_days = wp_list_pluck( $data['packages'], 'delivery_days' );
		$revisions     = wp_list_pluck( $data['packages'], 'revisions' );

		update_post_meta( $post_id, '_wpss_starting_price', min( $prices ) );
		update_post_meta( $post_id, '_wpss_fastest_delivery', min( $delivery_days ) );
		update_post_meta( $post_id, '_wpss_max_revisions', max( $revisions ) );
	}

	// Save FAQs.
	if ( ! empty( $data['faqs'] ) ) {
		update_post_meta( $post_id, '_wpss_faqs', $data['faqs'] );
	}

	// Save requirements.
	if ( ! empty( $data['requirements'] ) ) {
		update_post_meta( $post_id, '_wpss_requirements', $data['requirements'] );
	}

	// Save addons.
	if ( ! empty( $data['addons'] ) ) {
		update_post_meta( $post_id, '_wpss_addons', $data['addons'] );
	}

	// Save stats using standardized meta keys.
	if ( ! empty( $data['stats'] ) ) {
		update_post_meta( $post_id, '_wpss_view_count', $data['stats']['views'] );
		update_post_meta( $post_id, '_wpss_order_count', $data['stats']['orders'] );
		update_post_meta( $post_id, '_wpss_rating_average', $data['stats']['rating'] );
		update_post_meta( $post_id, '_wpss_rating_count', $data['stats']['reviews'] );
		update_post_meta( $post_id, '_wpss_review_count', $data['stats']['reviews'] );
	}

	// Insert actual review rows so the reviews table stays in sync with post meta.
	if ( ! empty( $data['stats'] ) && $data['stats']['reviews'] > 0 ) {
		global $wpdb, $demo_review_texts;

		$reviews_table = $wpdb->prefix . 'wpss_reviews';
		$ratings       = wpss_generate_rating_distribution(
			(float) $data['stats']['rating'],
			(int) $data['stats']['reviews']
		);
		$vendor_id     = (int) get_post_field( 'post_author', $post_id );
		$total         = count( $ratings );

		foreach ( $ratings as $i => $rating ) {
			// Spread reviews evenly over the past year.
			$days_ago   = (int) round( ( $i / $total ) * 365 );
			$created_at = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days_ago} days" ) );

			$wpdb->insert(
				$reviews_table,
				array(
					'order_id'    => 0,
					'reviewer_id' => 1,
					'reviewee_id' => $vendor_id,
					'service_id'  => $post_id,
					'customer_id' => 1,
					'vendor_id'   => $vendor_id,
					'rating'      => $rating,
					'review'      => $demo_review_texts[ $i % count( $demo_review_texts ) ],
					'status'      => 'approved',
					'created_at'  => $created_at,
				),
				array( '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s' )
			);
		}
	}

	// Set featured.
	if ( ! empty( $data['featured'] ) ) {
		update_post_meta( $post_id, '_wpss_featured', 1 );
	}

	return $post_id;
}

// Create all demo services.
$created = 0;
$errors  = 0;

WP_CLI::log( 'Creating ' . count( $demo_services ) . ' demo services...' );
WP_CLI::log( '' );

foreach ( $demo_services as $service ) {
	$result = wpss_create_demo_service( $service );

	if ( is_wp_error( $result ) ) {
		WP_CLI::warning( 'Failed: ' . $service['title'] . ' - ' . $result->get_error_message() );
		$errors++;
	} else {
		WP_CLI::success( 'Created: ' . $service['title'] . ' (ID: ' . $result . ')' );
		$created++;
	}
}

WP_CLI::log( '' );
WP_CLI::log( '========================================' );
WP_CLI::log( "Created: {$created} services" );
if ( $errors > 0 ) {
	WP_CLI::log( "Errors: {$errors}" );
}
WP_CLI::log( '========================================' );
