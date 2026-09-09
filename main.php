<?php
/**
 * Plugin Name: WordPress Article Generator
 * Description: สร้างบทความอัตโนมัติด้วย Gemini API (มีระบบ Auto-detect โมเดลที่ใช้งานได้)
 * Author: Jirakit Pawnsakungrungrot
 * Author URI: https://www.linkedin.com/in/sunny-jirakit
 * Plugin URI: https://github.com/sunny420x/wordpress-article-generator
 * GitHub Plugin URI: https://github.com/sunny420x/wordpress-article-generator
 * Primary Branch: master
 * Version: 1.0.0
 * License: GPL2
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// 1. เพิ่มเมนูในระบบหลังบ้าน (Admin Menu)
add_action( 'admin_menu', 'gemini_generator_add_admin_menu' );
function gemini_generator_add_admin_menu() {
    add_menu_page(
        'Articles Generator', 
        'Articles Generator', 
        'manage_options', 
        'article_generator', 
        'gemini_generator_display_admin_page', 
        'dashicons-welcome-write-blog', 
        20 
    );
}

// 2. ลงทะเบียน Settings 
add_action( 'admin_init', 'gemini_generator_register_settings' );
function gemini_generator_register_settings() {
    register_setting( 'gemini_generator_options', 'gemini_api_key' );
    register_setting( 'gemini_generator_options', 'gemini_model_name' );
    register_setting( 'gemini_generator_options', 'gemini_image_model_name' );
    register_setting( 'gemini_generator_options', 'call_to_action' );
    register_setting( 'gemini_generator_options', 'call_to_action_en' );
    register_setting( 'gemini_generator_options', 'site_context' );
}

// 3. ฟังก์ชั่นดึงรายชื่อโมเดลจาก API (เก็บ Cache 5 นาทีเพื่อความรวดเร็ว)
function gemini_get_available_models( $api_key ) {
    if ( empty( $api_key ) ) return [];

    $transient_key = 'gemini_api_models_' . md5( $api_key );
    $models = get_transient( $transient_key );

    if ( false === $models ) {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models?key=' . $api_key;
        $response = wp_remote_get( $url, [ 'timeout' => 15 ] );

        if ( ! is_wp_error( $response ) ) {
            $body = json_decode( wp_remote_retrieve_body( $response ), true );
            $models = [];
            
            if ( isset( $body['models'] ) ) {
                foreach ( $body['models'] as $m ) {
                    // กรองเอาเฉพาะโมเดลที่รองรับการสร้าง Text (generateContent)
                    if ( isset( $m['supportedGenerationMethods'] ) && in_array( 'generateContent', $m['supportedGenerationMethods'] ) ) {
                        $models[ $m['name'] ] = $m['displayName'] . ' (' . str_replace('models/', '', $m['name']) . ')';
                    }
                }
                set_transient( $transient_key, $models, 300 ); // Cache 5 นาที
            }
        }
    }
    return $models ?: [];
}

function gemini_get_available_image_models( $api_key ) {
    if ( empty( $api_key ) ) return [];

    $models = [];
    $available_models = gemini_get_available_models( $api_key );

    foreach ( $available_models as $model_name => $label ) {
        $search_text = strtolower( $model_name . ' ' . $label );
        if ( false !== strpos( $search_text, 'image' ) || false !== strpos( $search_text, 'banana' ) ) {
            $models[ $model_name ] = $label;
        }
    }

    return $models;
}

// 4. หน้า UI หลังบ้าน
function gemini_generator_display_admin_page() {
    $api_key = get_option( 'gemini_api_key' );
    $selected_model = get_option( 'gemini_model_name', 'models/gemini-1.5-flash' );
    $selected_image_model = get_option( 'gemini_image_model_name', '' );
    $available_models = gemini_get_available_models( $api_key );
    $available_image_models = gemini_get_available_image_models( $api_key );
    ?>
    <style>
        ul.popup_profile_list {
            margin: 0;
        }
        ul.popup_profile_list li {
            padding: 10px 20px;
            font-size: 14px;
            background: #f8f8f8;
            color: #111;
            transition: .2s ease-in-out;
            margin: 0;
        }
        ul.popup_profile_list li:hover {
            background: #fff;
            cursor: pointer;
        }
        .leftside {
            width: 350px;
            background: #f8f8f8;
            height: max-content;
        }
        .leftside h1 {
            background: #009FE3;
            color: #fff;
            font-size: 16px;
            padding: 10px 20px;
            margin: 0;
        }
        .leftside a {
            padding: 10px 20px;
            font-size: 14px;
            background: #f8f8f8;
            color: #000;
            transition: .2s ease-in-out;
            display: block;
            width: 100%;
            text-decoration: none;
        }
        .leftside a.active {
            background: #fff;
        }
        .leftside a:hover {
            background: #fff;
            cursor: pointer;
        }
        .container {
            width: 1200px;
            background: #fff;
        }
        .container h1 {
            background: #555;
            color: #fff;
            font-size: 16px;
            padding: 10px 20px;
            margin: 0;
        }
        .white-label-zone {
            width: calc(100% + 20px);
            height: auto;
            background: #fff;
            display: flex;
            margin: 0 0 0 -20px;
        }
        .white-label-zone {
            h1 {
                padding: 0 20px;
            }
            p {
                padding: 0 20px;
            }
        }
    </style>
    <div class="white-label-zone no-print">
        <!-- <span style="padding: 40px 10px 40px 40px;float: left;font-size: 60px;">📝</span> -->
        <img src="<?php echo esc_url( plugin_dir_url( __FILE__ ) . 'plugin-logo.jpg' ); ?>" " alt="Plugin Logo" style="width: 125px; height: auto; float: left; margin: 40px 10px 40px 20px; border-radius: 20px;">
        <div style="padding: 20px 0;">
            <h1>WordPress Articles Generator</h1>
            <p>ระบบสร้างบทความอัตโนมัติสำหรับ WordPress</p>
            <p>
            <strong>Github Repository:</strong> <a href="https://github.com/sunny420x/wordpress-article-generator" target="_blank">https://github.com/sunny420x/wordpress-article-generator</a><br>
            <!-- <strong>Documentation:</strong> <a href="https://github.com/sunny420x/wordpress-article-generator/wiki" target="_blank">https://github.com/sunny420x/wordpress-article-generator/wiki</a><br> -->
            <strong>Support:</strong> <a href="https://github.com/sunny420x/wordpress-article-generator/issues" target="_blank">https://github.com/sunny420x/wordpress-article-generator/issues</a><br>
            <strong>Developer:</strong> <a href="https://sunny420x.com" target="_blank">https://sunny420x.com</a>
            </p>
        </div>
    </div>
    <div class="wrap">
        <div style="display: flex;">
            <div class="leftside">
                <h1>WordPress Articles Generator</h1>
                <a href="admin.php?page=article_generator&option=create" <?php if(isset($_GET['option']) && $_GET['option'] == "create") { echo "class='active'"; } ?>>📝 สร้างบทความใหม่เลย</a>
                <a href="admin.php?page=article_generator&option=settings" <?php if(isset($_GET['option']) && $_GET['option'] == "settings") { echo "class='active'"; } ?>>⚙️ ตั้งค่าระบบ</a>
            </div>
            <div class="container">                
                <?php if(isset($_GET['option']) && $_GET['option'] == "settings") { ?>
                <h1>Gemini Article Generator Settings</h1>
                <div style="padding: 0 25px 25px 25px;">
                    <form method="post" action="options.php">
                        <?php settings_fields( 'gemini_generator_options' ); ?>
                        <table class="form-table">
                            <tr valign="top">
                                <th scope="row">Google Gemini API Key</th>
                                <td>
                                    <input type="password" name="gemini_api_key" value="<?php echo esc_attr( $api_key ); ?>" style="width: 100%; max-width: 400px;" />
                                    <p class="description">รับ API Key ได้ที่ <a href="https://aistudio.google.com/app/apikey" target="_blank">Google AI Studio</a></p>
                                </td>
                            </tr>
                            <tr valign="top">
                                <th scope="row">เลือกโมเดล (Text Model)</th>
                                <td>
                                    <select name="gemini_model_name" style="width: 100%; max-width: 400px;">
                                        <?php if ( empty( $available_models ) && !empty($api_key) ) : ?>
                                            <option value="">❌ ไม่พบโมเดลที่ใช้งานได้ (ลองตรวจสอบ API Key)</option>
                                        <?php elseif ( empty( $api_key ) ) : ?>
                                            <option value="">⚠️ กรุณาใส่ API Key แล้วกดบันทึกก่อน</option>
                                        <?php else : ?>
                                            <?php foreach ( $available_models as $val => $label ) : ?>
                                                <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $selected_model, $val ); ?>>
                                                    <?php echo esc_html( $label ); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>
                                    <p class="description">ระบบจะดึงรายชื่อโมเดลที่คุณมีสิทธิ์ใช้งานมาให้เลือกโดยอัตโนมัติ (แนะนำให้เลือกที่มีคำว่า Flash หรือ Pro)</p>
                                </td>
                            </tr>
                            <tr valign="top">
                                <th scope="row">เลือกโมเดล (Image Model)</th>
                                <td>
                                    <select name="gemini_image_model_name" style="width: 100%; max-width: 400px;">
                                        <?php if ( empty( $available_image_models ) && ! empty( $api_key ) ) : ?>
                                            <option value="">ไม่พบโมเดล Gemini สำหรับสร้างภาพ</option>
                                        <?php elseif ( empty( $api_key ) ) : ?>
                                            <option value="">กรุณาใส่ API Key แล้วกดบันทึกก่อน</option>
                                        <?php else : ?>
                                            <option value="">ไม่สร้างภาพปก</option>
                                            <?php foreach ( $available_image_models as $val => $label ) : ?>
                                                <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $selected_image_model, $val ); ?>>
                                                    <?php echo esc_html( $label ); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>
                                    <p class="description">ระบบจะแสดงเฉพาะโมเดล Gemini สำหรับสร้างภาพที่รองรับ generateContent เช่น Nano Banana</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">เนื้อหาของเว็บไซต์ (Site Context):</th>
                                <td>
                                    <input type="text" name="site_context" style="width: 100%; max-width: 400px;" value="<?=get_option('site_context')?>" />
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Call To Action สำหรับบทความภาษาไทย</th>
                                <td>
                                    <?php
                                    wp_editor( get_option('call_to_action', ''), 'call_to_action', array(
                                        'textarea_name' => 'call_to_action', // The 'name' attribute for the form submission
                                        'textarea_rows' => 15,                      // Number of visible rows
                                        'media_buttons' => true,                   // Show "Add Media" buttons
                                    ));
                                    ?>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Call To Action สำหรับบทความภาษาอังกฤษ</th>
                                <td>
                                    <?php
                                    wp_editor( get_option('call_to_action_en', ''), 'call_to_action_en', array(
                                        'textarea_name' => 'call_to_action_en', // The 'name' attribute for the form submission
                                        'textarea_rows' => 15,                      // Number of visible rows
                                        'media_buttons' => true,                   // Show "Add Media" buttons
                                    ));
                                    ?>
                                </td>
                            </tr>
                        </table>
                        <?php submit_button('บันทึกการตั้งค่า'); ?>
                    </form>
                </div>
                <?php } elseif(isset($_GET['option']) && $_GET['option'] == "create") { ?>
                <h1>Gemini Article Generator</h1>
                <!-- ฟอร์มสร้างบทความ -->
                <?php if ( ! empty( $api_key ) && ! empty( $available_models ) ) : ?>
                <div style="padding: 0 25px 25px 25px;">
                    <h2>✨ สร้างบทความใหม่</h2>
                    <div style="margin-bottom: 15px;">
                        <label for="gemini_topic"><strong>หัวข้อบทความ (Topic):</strong></label><br>
                        <select name="gemini_language" id="gemini_language">
                            <option value="ภาษาไทย" selected>ภาษาไทย</option>
                            <option value="ภาษาอังกฤษ">ภาษาอังกฤษ</option>
                        </select>
                        <input type="text" id="gemini_topic" style="width: 100%; max-width: 600px; margin-top: 5px; padding: 8px;" />
                    </div>
                    
                    <button id="btn_generate" class="button button-primary button-hero">สร้างบทความเดี๋ยวนี้</button>
                    <span id="gemini_status" style="margin-left: 10px; font-weight: bold; color: #2271b1;"></span>
                    
                    <div id="gemini_result" style="margin-top: 20px; padding: 15px; background: #f0f0f1; display: none; border-left: 4px solid #2271b1;"></div>
                </div>
                <?php endif; ?>

                <?php } else { ?>
                <h1>WordPress Articles Generator</h1>
                <div style="padding: 0 25px 25px 25px;">
                    <h2>ระบบนี้คืออะไร ?</h2>
                    <p><strong>WordPress Articles Generator</strong> เป็นปลั๊กอิน WordPress ที่ออกแบบมาเพื่อช่วยให้นักพัฒนาและเจ้าของเว็บไซต์สามารถสร้างบทความคุณภาพสูงพร้อมรูปภาพประกอบอัตโนมัติได้อย่างรวดเร็วผ่าน <strong>Google Gemini API</strong></p>
                    <h2>วิธีการติดตั้ง</h2>
                    <p>
                        สามารถติดตั้งปลั้กอินนี้ได้โดยการดาวน์โหลดไฟล์นี้จาก Github หน้านี้ และอัพโหลดลงในหน้า /wp-admin/plugin-install.php หลังจากอัพโหลด 
                        และเปิดใช้งาน (Activate) ระบบจะทำการสร้างตารางและคอลัมน์ใหม่จากตารางเดิมโดยอัตโนมัติ
                    </p>
                </div>
                <?php
                }
                ?>
            </div>
        </div>
    </div>

    <!-- สคริปต์ AJAX สำหรับส่งข้อมูล -->
    <script>
    jQuery(document).ready(function($) {
        $('#btn_generate').on('click', function(e) {
            e.preventDefault();
            
            var topic = $('#gemini_topic').val();
            var language = $('#gemini_language').val();

            if(!topic) {
                alert('กรุณาระบุหัวข้อบทความ');
                return;
            }

            if(!language) {
                alert('กรุณาระบุภาษาของบทความ')
                return;
            }

            var $btn = $(this);
            var $status = $('#gemini_status');
            var $result = $('#gemini_result');

            $btn.prop('disabled', true);
            $status.text('กำลังประมวลผล... (อาจใช้เวลา 15-30 วินาที)').css('color', '#2271b1');
            $result.hide();

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'gemini_generate_post_ajax',
                    topic: topic,
                    language: language,
                    security: '<?php echo wp_create_nonce("gemini_generate_nonce"); ?>'
                },
                success: function(response) {
                    $btn.prop('disabled', false);
                    if(response.success) {
                        $status.text('✅ สร้างบทความสำเร็จ!').css('color', 'green');
                        var image_notice = response.data.image_warning
                            ? '<br><span style="color:#996800;">' + $('<div>').text(response.data.image_warning).html() + '</span><br>'
                            : response.data.image_created
                                ? '<br><span style="color:green;">สร้างภาพปกและตั้งเป็น Featured Image แล้ว</span><br>'
                                : '<br><span>ไม่ได้สร้างภาพปก</span><br><span style="color:#996800;">(หากต้องการสร้างภาพปก กรุณาเลือกโมเดล Image Model ในหน้า Settings)</span><br>';
                        $result.html(
                            '<strong>สถานะ:</strong> สร้างเป็น Draft เรียบร้อย<br>' +
                            image_notice +
                            '<a href="' + response.data.edit_url + '" target="_blank" class="button button-primary" style="margin-top:10px;">ไปที่หน้าแก้ไขบทความ (Edit Post)</a>'
                        ).show();
                    } else {
                        $status.text('❌ เกิดข้อผิดพลาด').css('color', 'red');
                        $result.html('Error: ' + response.data).show();
                    }
                },
                error: function(xhr, status, error) {
                    $btn.prop('disabled', false);
                    $status.text('❌ เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์').css('color', 'red');
                    console.log(error);
                }
            });
        });
    });
    </script>
    <?php
}

// 5. ฟังก์ชั่นจัดการ AJAX สร้างโพสต์
add_action( 'wp_ajax_gemini_generate_post_ajax', 'gemini_generate_post_handler' );
function gemini_generate_post_handler() {
    // 1. ตรวจสอบความปลอดภัยและสิทธิ์ผู้ใช้
    check_ajax_referer( 'gemini_generate_nonce', 'security' );

    if ( ! current_user_can( 'publish_posts' ) ) {
        wp_send_json_error( 'คุณไม่มีสิทธิ์สร้างบทความ' );
    }

    $api_key = get_option( 'gemini_api_key' );
    $model_name = get_option( 'gemini_model_name' );
    $image_model_name = get_option( 'gemini_image_model_name', '' );
    
    if ( empty( $api_key ) || empty($model_name ) ) {
        wp_send_json_error( 'กรุณาตั้งค่า API Key และเลือกโมเดลก่อน' );
    }

    $topic = isset( $_POST['topic'] ) ? sanitize_text_field( wp_unslash( $_POST['topic'] ) ) : '';
    $language = isset( $_POST['language'] ) ? sanitize_text_field( wp_unslash( $_POST['language'] ) ) : 'ภาษาไทย';

    if ( empty( $topic ) ) {
        wp_send_json_error( 'กรุณาระบุหัวข้อบทความ' );
    }

    // เงื่อนไขการเลือก Call To Action
    $cta = get_option('call_to_action');
    if($language == "ภาษาอังกฤษ") {
        $cta = get_option('call_to_action_en');
    }

    $site_context = get_option('site_context');

    $text_endpoint = 'https://generativelanguage.googleapis.com/v1beta/' . $model_name . ':generateContent?key=' . $api_key;
    
    $prompt_text = "เขียนบทความบล็อก {$language} ที่มีคุณภาพสูงและอ่านง่าย สำหรับเว็บไซต์ที่เน้นเนื้อหาหมวดหมู่ {$site_context} มีการ Optimize สำหรับ SEO และ AEO เกี่ยวกับหัวข้อ: '{$topic}' 
    โดยจัดรูปแบบเป็น HTML ให้พร้อมใช้งาน ใช้อย่างน้อย <h2>, <h3>, <p>, <ul> ไม่ต้องครอบด้วยแท็ก <html> <body> หรือ markdown code block และเนื้อหามีความยาวอย่างน้อย 600 คำ 
    สามารถใช้ตารางเปรียบเทียบข้อมูลได้ (Optional) ตามความเหมาะสมของข้อมูล มีการอ้างอิงข้อมูลท้ายบทความแบบ APA6";

    $text_body = [
        'contents' => [ ['parts' => [ ['text' => $prompt_text] ] ] ]
    ];

    $text_response = wp_remote_post($text_endpoint, [
        'headers' => ['Content-Type' => 'application/json'],
        'body'    => wp_json_encode( $text_body ),
        'timeout' => 60, // เผื่อเวลาให้ AI คิดสัก 60 วินาที
    ]);

    if ( is_wp_error( $text_response ) ) {
        wp_send_json_error( 'การเชื่อมต่อขัดข้อง: ' . $text_response->get_error_message() );
    }

    $text_data = json_decode( wp_remote_retrieve_body($text_response ), true );
    
    if ( isset( $text_data['error'] ) ) {
        wp_send_json_error( 'Gemini API Error: ' . $text_data['error']['message'] );
    }

    // ดึงข้อความออกมาและทำความสะอาด (ลบ ```html ที่ AI ชอบแถมมา)
    $generated_content = $text_data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    $generated_content = preg_replace('/^```html\s*|```\s*$/i', '', trim($generated_content));

    if(empty($generated_content)) {
        wp_send_json_error( 'API ส่งค่าว่างกลับมา ลองเปลี่ยนโมเดลดูนะครับ' );
    }

    // 3. สร้างบทความลง WordPress (บันทึกเป็น Draft)
    $post_data = [
        'post_title'   => $topic,
        'post_content' => $generated_content . "\n\n" . $cta,
        'post_status'  => 'draft', // แนะนำให้เป็น draft ไว้ก่อน เพื่อให้เราเข้าไปใส่ภาพปกเองแล้วค่อยกด Publish
        'post_author'  => get_current_user_id(),
        'post_type'    => 'post'
    ];

    $post_id = wp_insert_post( $post_data );

    if ( is_wp_error( $post_id ) ) {
        wp_send_json_error( 'บันทึกบทความลง WordPress ไม่สำเร็จ' );
    }

    $image_error = '';
    $image_created = false;

    if ( ! empty( $image_model_name ) ) {
        $image_endpoint = 'https://generativelanguage.googleapis.com/v1beta/' . $image_model_name . ':generateContent?key=' . rawurlencode( $api_key );
        $image_response = wp_remote_post( $image_endpoint, [
            'headers' => [ 'Content-Type' => 'application/json' ],
            'body'    => wp_json_encode([
                'contents' => [
                    [
                        'parts' => [
                            [
                                'text' => 'Create a professional editorial cover image for a blog article titled "' . $topic . '". No text, letters, logos, watermarks, or UI elements. Match the subject and language-neutral visual meaning of the topic. Clean composition, suitable for a WordPress featured image.',
                            ],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'responseModalities' => [ 'TEXT', 'IMAGE' ],
                ],
            ]),
            'timeout' => 120,
        ]);

        if ( is_wp_error( $image_response ) ) {
            $image_error = 'เชื่อมต่อ Gemini Image API ไม่สำเร็จ: ' . $image_response->get_error_message();
        } else {
            $image_data = json_decode( wp_remote_retrieve_body( $image_response ), true );
            $image_part = [];

            foreach ( $image_data['candidates'][0]['content']['parts'] ?? [] as $part ) {
                if ( ! empty( $part['inlineData']['data'] ) ) {
                    $image_part = $part['inlineData'];
                    break;
                }
            }

            if ( isset( $image_data['error']['message'] ) ) {
                $image_error = 'Gemini Image API Error: ' . $image_data['error']['message'];
            } elseif ( ! empty( $image_part['data'] ) ) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                require_once ABSPATH . 'wp-admin/includes/media.php';
                require_once ABSPATH . 'wp-admin/includes/image.php';

                $mime_type = $image_part['mimeType'] ?? 'image/png';
                $file_extension = 'image/jpeg' === $mime_type ? 'jpg' : ( 'image/webp' === $mime_type ? 'webp' : 'png' );
                $temporary_file = wp_tempnam( $topic . '.' . $file_extension );
                $decoded_image = base64_decode( $image_part['data'], true );
                $image_saved = $temporary_file && false !== $decoded_image && file_put_contents( $temporary_file, $decoded_image ) !== false;

                if ( ! $image_saved ) {
                    $image_error = 'ถอดรหัสหรือบันทึกภาพจาก Gemini ไม่สำเร็จ';
                    if ( $temporary_file ) {
                        @unlink( $temporary_file );
                    }
                } else {
                    $image_file = [
                        'name'     => sanitize_file_name( $topic ) . '.' . $file_extension,
                        'type'     => $mime_type,
                        'tmp_name' => $temporary_file,
                        'error'    => 0,
                        'size'     => filesize( $temporary_file ),
                    ];
                    $attachment_id = media_handle_sideload( $image_file, $post_id, $topic );

                    if ( is_wp_error( $attachment_id ) ) {
                        $image_error = 'นำภาพเข้า Media Library ไม่สำเร็จ: ' . $attachment_id->get_error_message();
                        @unlink( $temporary_file );
                    } else {
                        set_post_thumbnail( $post_id, $attachment_id );
                        $image_created = true;
                    }
                }
            } else {
                $image_error = 'Gemini ไม่ได้ส่งข้อมูลภาพกลับมา หรือโมเดลนี้ไม่รองรับการสร้างภาพ';
            }
        }
    }

    // 4. ส่งค่าความสำเร็จและ URL สำหรับให้ User กดเข้าไปหน้าแก้ไข
    $result = [
        'post_id'  => $post_id,
        'edit_url' => admin_url( 'post.php?post=' . $post_id . '&action=edit' ),
        'image_created' => $image_created,
    ];

    if ( ! empty( $image_error ) ) {
        $result['image_warning'] = $image_error;
    }

    wp_send_json_success( $result );
}