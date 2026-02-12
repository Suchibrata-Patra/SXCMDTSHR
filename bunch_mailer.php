<?php
// htdocs/bunch_mailer.php
session_start();

// Security check: Redirect to login if session credentials do not exist
if (!isset($_SESSION['smtp_user']) || !isset($_SESSION['smtp_pass'])) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php
        define('PAGE_TITLE', 'SXC MDTS | Mailmerge');
        include 'header.php';
    ?>
    <style>
        :root {
            --apple-blue: #007AFF;
            --apple-green: #34C759;
            --apple-red: #FF3B30;
            --apple-orange: #FF9500;
            --apple-purple: #AF52DE;
            --apple-teal: #5AC8FA;
            --apple-pink: #FF2D55;
            --apple-gray: #8E8E93;
            --apple-light-gray: #C7C7CC;
            --apple-bg: #F2F2F7;
            --glass: rgba(255, 255, 255, 0.75);
            --glass-border: rgba(255, 255, 255, 0.25);
            --border: #E5E5EA;
            --success-green: #34C759;
            --card-shadow: 0 1px 3px rgba(0, 0, 0, 0.04), 0 8px 24px rgba(0, 0, 0, 0.06);
            --hover-shadow: 0 4px 12px rgba(0, 0, 0, 0.08), 0 16px 48px rgba(0, 0, 0, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Display', 'Inter', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e8ecf1 100%);
            color: #1c1c1e;
            display: flex;
            height: 100vh;
            overflow: hidden;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* ========== MAIN LAYOUT ========== */
        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .content-area {
            flex: 1;
            overflow-y: auto;
            background: transparent;
            scroll-behavior: smooth;
        }

        /* Custom Scrollbar */
        .content-area::-webkit-scrollbar {
            width: 8px;
        }

        .content-area::-webkit-scrollbar-track {
            background: transparent;
        }

        .content-area::-webkit-scrollbar-thumb {
            background: rgba(0, 0, 0, 0.15);
            border-radius: 10px;
        }

        .content-area::-webkit-scrollbar-thumb:hover {
            background: rgba(0, 0, 0, 0.25);
        }

        /* ========== HEADER ========== */
        .page-header {
            background: var(--glass);
            backdrop-filter: saturate(180%) blur(20px);
            -webkit-backdrop-filter: saturate(180%) blur(20px);
            border-bottom: 1px solid var(--glass-border);
            padding: 28px 40px;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-container {
            max-width: 1600px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-left h1 {
            font-size: 32px;
            font-weight: 700;
            color: #1c1c1e;
            letter-spacing: -0.8px;
            margin-bottom: 8px;
            background: linear-gradient(135deg, #1c1c1e 0%, #4a4a4a 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .header-left p {
            font-size: 15px;
            color: var(--apple-gray);
            font-weight: 400;
            letter-spacing: -0.2px;
        }

        .header-stats {
            display: flex;
            gap: 16px;
        }

        .stat-item {
            text-align: center;
            padding: 16px 24px;
            background: white;
            border-radius: 14px;
            border: 1px solid var(--border);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            min-width: 110px;
        }

        .stat-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.12);
        }

        .stat-value {
            font-size: 30px;
            font-weight: 800;
            color: #1c1c1e;
            line-height: 1;
            margin-bottom: 6px;
            letter-spacing: -0.5px;
        }

        .stat-label {
            font-size: 11px;
            color: var(--apple-gray);
            text-transform: uppercase;
            letter-spacing: 0.8px;
            font-weight: 600;
        }

        .stat-item.pending .stat-value {
            background: linear-gradient(135deg, var(--apple-orange) 0%, #FFB340 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-item.completed .stat-value {
            background: linear-gradient(135deg, var(--apple-green) 0%, #4CD964 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-item.failed .stat-value {
            background: linear-gradient(135deg, var(--apple-red) 0%, #FF6B6B 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* ========== TABS ========== */
        .tabs-container {
            background: white;
            border-bottom: 1px solid var(--border);
            position: sticky;
            top: 109px;
            z-index: 99;
            box-shadow: 0 1px 0 rgba(0, 0, 0, 0.03);
        }

        .tabs {
            max-width: 1600px;
            margin: 0 auto;
            display: flex;
            padding: 0 40px;
            gap: 8px;
        }

        .tab {
            padding: 16px 24px;
            font-size: 14px;
            font-weight: 500;
            color: var(--apple-gray);
            background: none;
            border: none;
            border-bottom: 3px solid transparent;
            cursor: pointer;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            letter-spacing: -0.1px;
        }

        .tab:hover {
            color: #1c1c1e;
            background: rgba(0, 0, 0, 0.02);
        }

        .tab.active {
            color: var(--apple-blue);
            border-bottom-color: var(--apple-blue);
            font-weight: 600;
        }

        /* ========== CONTAINER ========== */
        .container {
            max-width: 1600px;
            margin: 0 auto;
            padding: 32px 40px 80px;
        }

        .tab-content {
            display: none;
            animation: fadeIn 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .tab-content.active {
            display: block;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(16px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* ========== TWO COLUMN LAYOUT ========== */
        .compose-layout {
            display: grid;
            grid-template-columns: 1fr 450px;
            gap: 28px;
            margin-bottom: 24px;
        }

        .compose-layout > div {
            min-width: 0;
            overflow: hidden;
        }

        /* ========== CARDS ========== */
        .card {
            background: white;
            border-radius: 18px;
            padding: 36px;
            margin-bottom: 24px;
            border: 1px solid var(--border);
            box-shadow: var(--card-shadow);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .card:hover {
            box-shadow: var(--hover-shadow);
            transform: translateY(-2px);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 28px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border);
        }

        .card-title {
            font-size: 22px;
            font-weight: 700;
            color: #1c1c1e;
            letter-spacing: -0.5px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .card-title .material-icons {
            font-size: 26px;
            background: linear-gradient(135deg, var(--apple-blue) 0%, var(--apple-purple) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .card-subtitle {
            font-size: 14px;
            color: var(--apple-gray);
            margin-top: 8px;
            font-weight: 400;
            letter-spacing: -0.1px;
        }

        /* ========== UPLOAD ZONE ========== */
        .upload-zone {
            border: 3px dashed var(--border);
            border-radius: 16px;
            padding: 56px 40px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background: linear-gradient(135deg, #fafbfc 0%, #f5f6f8 100%);
            position: relative;
            overflow: hidden;
        }

        .upload-zone::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 50% 50%, rgba(0, 122, 255, 0.03) 0%, transparent 70%);
            opacity: 0;
            transition: opacity 0.3s;
        }

        .upload-zone:hover::before {
            opacity: 1;
        }

        .upload-zone:hover {
            border-color: var(--apple-blue);
            background: linear-gradient(135deg, #f0f7ff 0%, #e5f2ff 100%);
            transform: scale(1.01);
        }

        .upload-zone.dragover {
            border-color: var(--apple-green);
            background: linear-gradient(135deg, #e8f9f0 0%, #d4f3e1 100%);
            transform: scale(1.02);
            box-shadow: 0 8px 32px rgba(52, 199, 89, 0.15);
        }

        .upload-icon {
            font-size: 72px;
            margin-bottom: 20px;
            background: linear-gradient(135deg, var(--apple-blue) 0%, var(--apple-purple) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .upload-title {
            font-size: 20px;
            font-weight: 700;
            color: #1c1c1e;
            margin-bottom: 10px;
            letter-spacing: -0.3px;
        }

        .upload-subtitle {
            font-size: 15px;
            color: var(--apple-gray);
            line-height: 1.5;
        }

        /* ========== FILE ATTACHMENT PANEL ========== */
        .attachment-panel {
            background: white;
            border-radius: 18px;
            padding: 36px;
            border: 1px solid var(--border);
            box-shadow: var(--card-shadow);
            height: fit-content;
            position: sticky;
            top: 220px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .attachment-panel:hover {
            box-shadow: var(--hover-shadow);
        }

        .attachment-header {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 28px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border);
        }

        .attachment-header .material-icons {
            font-size: 30px;
            background: linear-gradient(135deg, var(--apple-teal) 0%, var(--apple-blue) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .attachment-header-text h3 {
            font-size: 20px;
            font-weight: 700;
            color: #1c1c1e;
            margin-bottom: 6px;
            letter-spacing: -0.3px;
        }

        .attachment-header-text p {
            font-size: 13px;
            color: var(--apple-gray);
        }

        .attachment-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 24px;
            background: var(--apple-bg);
            padding: 6px;
            border-radius: 12px;
        }

        .attachment-tab {
            flex: 1;
            padding: 12px;
            background: transparent;
            border: none;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            color: var(--apple-gray);
            cursor: pointer;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .attachment-tab:hover {
            color: #1c1c1e;
            background: rgba(0, 0, 0, 0.03);
        }

        .attachment-tab.active {
            background: white;
            color: var(--apple-blue);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .attachment-tab .material-icons {
            font-size: 18px;
        }

        .attachment-content {
            display: none;
        }

        .attachment-content.active {
            display: block;
        }

        /* Drive Files List */
        .drive-files-list {
            max-height: 450px;
            overflow-y: auto;
            margin-top: 20px;
            padding-right: 4px;
        }

        .drive-files-list::-webkit-scrollbar {
            width: 6px;
        }

        .drive-files-list::-webkit-scrollbar-track {
            background: var(--apple-bg);
            border-radius: 10px;
        }

        .drive-files-list::-webkit-scrollbar-thumb {
            background: rgba(0, 0, 0, 0.2);
            border-radius: 10px;
        }

        .drive-file-item {
            display: flex;
            align-items: center;
            padding: 14px;
            border: 1.5px solid var(--border);
            border-radius: 12px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            background: white;
        }

        .drive-file-item:hover {
            border-color: var(--apple-blue);
            background: #F0F7FF;
            transform: translateX(4px);
        }

        .drive-file-item.selected {
            border-color: var(--apple-blue);
            background: linear-gradient(135deg, #E5F2FF 0%, #F0F7FF 100%);
            box-shadow: 0 0 0 3px rgba(0, 122, 255, 0.1);
        }

        .drive-file-icon {
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--apple-bg);
            border-radius: 10px;
            margin-right: 14px;
            font-size: 22px;
        }

        .drive-file-info {
            flex: 1;
            min-width: 0;
        }

        .drive-file-name {
            font-size: 14px;
            font-weight: 600;
            color: #1c1c1e;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            margin-bottom: 4px;
        }

        .drive-file-size {
            font-size: 12px;
            color: var(--apple-gray);
        }

        .drive-file-check {
            width: 26px;
            height: 26px;
            border: 2.5px solid var(--border);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .drive-file-item.selected .drive-file-check {
            background: var(--apple-blue);
            border-color: var(--apple-blue);
        }

        .drive-file-item.selected .drive-file-check .material-icons {
            color: white;
            font-size: 16px;
        }

        /* Upload Zone Small */
        .upload-zone-small {
            border: 2px dashed var(--border);
            border-radius: 14px;
            padding: 36px 28px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background: linear-gradient(135deg, #fafbfc 0%, #f5f6f8 100%);
        }

        .upload-zone-small:hover {
            border-color: var(--apple-blue);
            background: linear-gradient(135deg, #f0f7ff 0%, #e5f2ff 100%);
            transform: scale(1.02);
        }

        .upload-zone-small .material-icons {
            font-size: 52px;
            margin-bottom: 14px;
            background: linear-gradient(135deg, var(--apple-blue) 0%, var(--apple-teal) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .upload-zone-small p {
            font-size: 14px;
            color: #1c1c1e;
            margin-bottom: 6px;
            font-weight: 600;
        }

        .upload-zone-small small {
            font-size: 12px;
            color: var(--apple-gray);
        }

        /* Selected Attachment Display */
        .selected-attachment {
            background: linear-gradient(135deg, #E5F2FF 0%, #F0F7FF 100%);
            border: 2px solid var(--apple-blue);
            border-radius: 14px;
            padding: 20px;
            margin-top: 20px;
            display: none;
        }

        .selected-attachment.active {
            display: block;
            animation: slideIn 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .selected-attachment-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 14px;
        }

        .selected-attachment-title {
            font-size: 12px;
            font-weight: 700;
            color: var(--apple-blue);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .clear-attachment {
            background: transparent;
            border: none;
            color: var(--apple-red);
            cursor: pointer;
            padding: 6px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            transition: all 0.2s;
            font-weight: 600;
        }

        .clear-attachment:hover {
            background: rgba(255, 59, 48, 0.15);
        }

        .selected-attachment-info {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .selected-attachment-icon {
            width: 52px;
            height: 52px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: white;
            border-radius: 12px;
            font-size: 26px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .selected-attachment-details {
            flex: 1;
        }

        .selected-attachment-name {
            font-size: 15px;
            font-weight: 700;
            color: #1c1c1e;
            margin-bottom: 6px;
        }

        .selected-attachment-size {
            font-size: 12px;
            color: var(--apple-gray);
            font-weight: 500;
        }

        /* ========== FORM GROUPS ========== */
        .form-group {
            margin-bottom: 24px;
        }

        .form-group label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            color: #1c1c1e;
            font-size: 14px;
            letter-spacing: -0.1px;
        }

        input[type="text"],
        input[type="email"],
        textarea,
        select {
            width: 100%;
            padding: 14px 16px;
            border: 1.5px solid var(--border);
            border-radius: 12px;
            font-size: 15px;
            font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Display', 'Inter', sans-serif;
            background: white;
            color: #1c1c1e;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }

        input[type="text"]:focus,
        input[type="email"]:focus,
        textarea:focus,
        select:focus {
            outline: none;
            border-color: var(--apple-blue);
            box-shadow: 0 0 0 4px rgba(0, 122, 255, 0.1);
        }

        input::placeholder,
        textarea::placeholder {
            color: var(--apple-gray);
        }

        /* ========== BUTTONS ========== */
        .btn {
            padding: 14px 28px;
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex;
            align-items: center;
            gap: 10px;
            letter-spacing: -0.2px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--apple-blue) 0%, #0051D5 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(0, 122, 255, 0.25);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 122, 255, 0.35);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .btn-success {
            background: linear-gradient(135deg, var(--apple-green) 0%, #2EB84E 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(52, 199, 89, 0.25);
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(52, 199, 89, 0.35);
        }

        .btn-danger {
            background: linear-gradient(135deg, var(--apple-red) 0%, #D32F2F 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(255, 59, 48, 0.25);
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(255, 59, 48, 0.35);
        }

        .btn-secondary {
            background: white;
            color: #1c1c1e;
            border: 1.5px solid var(--border);
        }

        .btn-secondary:hover {
            background: var(--apple-bg);
            border-color: var(--apple-gray);
        }

        .btn .material-icons {
            font-size: 20px;
        }

        /* ========== ALERTS ========== */
        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 14px;
            animation: slideDown 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert .material-icons {
            font-size: 24px;
        }

        .alert-success {
            background: linear-gradient(135deg, #D4F3E1 0%, #E8F9F0 100%);
            border: 1.5px solid var(--apple-green);
            color: #1c6b3d;
        }

        .alert-success .material-icons {
            color: var(--apple-green);
        }

        .alert-error {
            background: linear-gradient(135deg, #FFE5E5 0%, #FFF0F0 100%);
            border: 1.5px solid var(--apple-red);
            color: #8b1e1e;
        }

        .alert-error .material-icons {
            color: var(--apple-red);
        }

        .alert-info {
            background: linear-gradient(135deg, #E5F2FF 0%, #F0F7FF 100%);
            border: 1.5px solid var(--apple-blue);
            color: #1a4d8f;
        }

        .alert-info .material-icons {
            color: var(--apple-blue);
        }

        /* ========== PROCESSING PROGRESS ========== */
        .processing-progress {
            background: white;
            border-radius: 16px;
            padding: 28px 32px;
            margin-bottom: 24px;
            border: 1.5px solid var(--border);
            box-shadow: var(--card-shadow);
            display: none;
        }

        .processing-progress.active {
            display: block;
            animation: slideDown 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .progress-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 18px;
        }

        .progress-header .material-icons {
            font-size: 28px;
            color: var(--apple-blue);
            animation: spin 2s linear infinite;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .progress-text {
            font-size: 15px;
            font-weight: 600;
            color: #1c1c1e;
        }

        .progress-bar-container {
            width: 100%;
            height: 8px;
            background: var(--apple-bg);
            border-radius: 10px;
            overflow: hidden;
        }

        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, var(--apple-blue) 0%, var(--apple-purple) 100%);
            border-radius: 10px;
            transition: width 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            width: 0%;
        }

        /* ========== QUEUE TABLE ========== */
        .queue-table-container {
            overflow-x: auto;
            border-radius: 16px;
            border: 1px solid var(--border);
        }

        .queue-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        .queue-table thead {
            background: linear-gradient(135deg, #f8f9ff 0%, #ffffff 100%);
        }

        .queue-table th {
            text-align: left;
            padding: 18px 20px;
            font-size: 12px;
            font-weight: 700;
            color: var(--apple-gray);
            text-transform: uppercase;
            letter-spacing: 0.8px;
            border-bottom: 1.5px solid var(--border);
        }

        .queue-table td {
            padding: 18px 20px;
            font-size: 14px;
            color: #1c1c1e;
            border-bottom: 1px solid var(--border);
        }

        .queue-table tr:last-child td {
            border-bottom: none;
        }

        .queue-table tbody tr {
            transition: background 0.2s;
        }

        .queue-table tbody tr:hover {
            background: var(--apple-bg);
        }

        .status-badge {
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-block;
        }

        .status-pending {
            background: linear-gradient(135deg, #FFF4E5 0%, #FFEBCC 100%);
            color: #B85C00;
        }

        .status-sent {
            background: linear-gradient(135deg, #D4F3E1 0%, #C0EDD2 100%);
            color: #1C6B3D;
        }

        .status-failed {
            background: linear-gradient(135deg, #FFE5E5 0%, #FFD6D6 100%);
            color: #8B1E1E;
        }

        /* ========== EMPTY STATE ========== */
        .empty-state {
            text-align: center;
            padding: 80px 40px;
            color: var(--apple-gray);
        }

        .empty-state-icon {
            font-size: 72px;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .empty-state h3 {
            font-size: 20px;
            font-weight: 700;
            color: #1c1c1e;
            margin-bottom: 10px;
        }

        .empty-state p {
            font-size: 15px;
            color: var(--apple-gray);
        }

        /* ========== COLUMN MAPPING ========== */
        .mapping-row {
            display: grid;
            grid-template-columns: 180px 1fr;
            gap: 16px;
            align-items: center;
            padding: 14px;
            background: var(--apple-bg);
            border-radius: 10px;
            margin-bottom: 10px;
        }

        .mapping-label {
            font-size: 13px;
            font-weight: 700;
            color: #1c1c1e;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 1200px) {
            .compose-layout {
                grid-template-columns: 1fr;
            }

            .attachment-panel {
                position: static;
            }
        }

        @media (max-width: 768px) {
            .header-stats {
                display: none;
            }

            .container {
                padding: 20px;
            }

            .card {
                padding: 24px;
            }
        }
    </style>
</head>

<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="content-area">
            <!-- Header -->
            <div class="page-header">
                <div class="header-container">
                    <div class="header-left">
                        <h1>📨 Bulk Mail Composer</h1>
                        <p>Send personalized emails to multiple recipients with ease</p>
                    </div>
                    <div class="header-stats">
                        <div class="stat-item pending">
                            <div class="stat-value" id="stat-pending">0</div>
                            <div class="stat-label">Pending</div>
                        </div>
                        <div class="stat-item completed">
                            <div class="stat-value" id="stat-completed">0</div>
                            <div class="stat-label">Completed</div>
                        </div>
                        <div class="stat-item failed">
                            <div class="stat-value" id="stat-failed">0</div>
                            <div class="stat-label">Failed</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <div class="tabs-container">
                <div class="tabs">
                    <button class="tab active" data-tab="compose">
                        📝 Compose
                    </button>
                    <button class="tab" data-tab="queue">
                        📋 Queue
                    </button>
                </div>
            </div>

            <!-- Container -->
            <div class="container">
                <!-- Compose Tab -->
                <div id="compose-tab" class="tab-content active">
                    <div class="compose-layout">
                        <!-- Left Column: CSV Upload & Preview -->
                        <div>
                            <div class="card">
                                <div class="card-header">
                                    <div>
                                        <div class="card-title">
                                            <span class="material-icons">table_chart</span>
                                            CSV Data Source
                                        </div>
                                        <div class="card-subtitle">Upload your recipient list with email addresses</div>
                                    </div>
                                </div>

                                <!-- Upload Zone -->
                                <div class="upload-zone" id="uploadZone">
                                    <div class="material-icons upload-icon">cloud_upload</div>
                                    <div class="upload-title">Drag & Drop CSV File</div>
                                    <div class="upload-subtitle">or click to browse • Max 10MB</div>
                                    <input type="file" id="csvFileInput" accept=".csv" style="display: none;">
                                </div>

                                <!-- Analysis Results -->
                                <div id="analysisResults" class="analysis-results">
                                    <div class="analysis-header">
                                        <div class="analysis-info">
                                            <h3>CSV Loaded Successfully</h3>
                                            <p id="analysisFileName">filename.csv</p>
                                        </div>
                                        <div class="analysis-stats">
                                            <div class="analysis-stat">
                                                <div class="analysis-stat-value" id="totalRecords">0</div>
                                                <div class="analysis-stat-label">Records</div>
                                            </div>
                                            <div class="analysis-stat">
                                                <div class="analysis-stat-value" id="validEmails">0</div>
                                                <div class="analysis-stat-label">Valid</div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Column Mapping -->
                                    <div class="column-mapping">
                                        <div class="mapping-header">📌 Column Mapping</div>
                                        <div class="mapping-grid" id="columnMapping"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Email Template Card -->
                            <div class="card">
                                <div class="card-header">
                                    <div>
                                        <div class="card-title">
                                            <span class="material-icons">mail_outline</span>
                                            Email Template
                                        </div>
                                        <div class="card-subtitle">Design your personalized message</div>
                                    </div>
                                </div>

                                <form id="bulkMailForm">
                                    <div class="form-group">
                                        <label>Subject Line</label>
                                        <input type="text" id="subject" placeholder="Enter email subject..." required>
                                    </div>

                                    <div class="form-group">
                                        <label>Message Body</label>
                                        <textarea id="message" rows="8" placeholder="Write your message here... Use {column_name} for personalization"></textarea>
                                    </div>

                                    <button type="button" class="btn btn-primary" id="addToQueueBtn">
                                        <span class="material-icons">add_circle</span>
                                        Add to Queue
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- Right Column: Attachments -->
                        <div>
                            <div class="attachment-panel">
                                <div class="attachment-header">
                                    <span class="material-icons">attach_file</span>
                                    <div class="attachment-header-text">
                                        <h3>Attachments</h3>
                                        <p>Add files to your email</p>
                                    </div>
                                </div>

                                <!-- Attachment Tabs -->
                                <div class="attachment-tabs">
                                    <button class="attachment-tab active" data-tab="drive">
                                        <span class="material-icons">cloud</span>
                                        Google Drive
                                    </button>
                                    <button class="attachment-tab" data-tab="upload">
                                        <span class="material-icons">upload_file</span>
                                        Upload
                                    </button>
                                </div>

                                <!-- Drive Content -->
                                <div id="drive-content" class="attachment-content active">
                                    <div class="drive-files-list" id="driveFilesList">
                                        <div class="empty-state">
                                            <div class="empty-state-icon">☁️</div>
                                            <p>Loading files...</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Upload Content -->
                                <div id="upload-content" class="attachment-content">
                                    <div class="upload-zone-small" id="attachmentUploadZone">
                                        <div class="material-icons">cloud_upload</div>
                                        <p>Drop files here</p>
                                        <small>or click to browse</small>
                                        <input type="file" id="attachmentFileInput" style="display: none;">
                                    </div>
                                </div>

                                <!-- Selected Attachment Display -->
                                <div id="selectedAttachment" class="selected-attachment">
                                    <div class="selected-attachment-header">
                                        <div class="selected-attachment-title">Selected File</div>
                                        <button class="clear-attachment" id="clearAttachmentBtn">
                                            <span class="material-icons">close</span>
                                        </button>
                                    </div>
                                    <div class="selected-attachment-info">
                                        <div class="selected-attachment-icon">
                                            <span class="material-icons" id="selectedFileIcon">description</span>
                                        </div>
                                        <div class="selected-attachment-details">
                                            <div class="selected-attachment-name" id="selectedFileName">File Name</div>
                                            <div class="selected-attachment-size" id="selectedFileSize">0 KB</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Queue Tab -->
                <div id="queue-tab" class="tab-content">
                    <!-- Processing Progress -->
                    <div id="processingProgress" class="processing-progress">
                        <div class="progress-header">
                            <span class="material-icons">hourglass_empty</span>
                            <div class="progress-text" id="progressText">Processing emails...</div>
                        </div>
                        <div class="progress-bar-container">
                            <div class="progress-bar" id="progressFill"></div>
                        </div>
                    </div>

                    <!-- Queue Controls -->
                    <div class="card">
                        <div class="card-header">
                            <div>
                                <div class="card-title">
                                    <span class="material-icons">send</span>
                                    Email Queue Management
                                </div>
                                <div class="card-subtitle">Review and send your queued emails</div>
                            </div>
                            <div style="display: flex; gap: 12px;">
                                <button class="btn btn-success" id="processQueueBtn">
                                    <span class="material-icons">play_arrow</span>
                                    Process Queue
                                </button>
                                <button class="btn btn-danger" onclick="clearQueue()">
                                    <span class="material-icons">delete_sweep</span>
                                    Clear Queue
                                </button>
                            </div>
                        </div>

                        <!-- Queue Table -->
                        <div class="queue-table-container" id="queueTableContainer">
                            <div class="empty-state">
                                <div class="empty-state-icon">📭</div>
                                <h3>No emails in queue</h3>
                                <p>Upload a CSV file to get started</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // ========== TAB SWITCHING ==========
        const tabs = document.querySelectorAll('.tab');
        const tabContents = document.querySelectorAll('.tab-content');

        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                const targetTab = tab.dataset.tab;

                tabs.forEach(t => t.classList.remove('active'));
                tab.classList.add('active');

                tabContents.forEach(content => content.classList.remove('active'));
                document.getElementById(targetTab + '-tab').classList.add('active');
            });
        });

        // ========== ATTACHMENT TABS ==========
        const attachmentTabs = document.querySelectorAll('.attachment-tab');
        const attachmentContents = document.querySelectorAll('.attachment-content');

        attachmentTabs.forEach(tab => {
            tab.addEventListener('click', () => {
                const targetTab = tab.dataset.tab;

                attachmentTabs.forEach(t => t.classList.remove('active'));
                tab.classList.add('active');

                attachmentContents.forEach(content => content.classList.remove('active'));
                document.getElementById(targetTab + '-content').classList.add('active');
            });
        });

        // ========== CSV UPLOAD ==========
        const uploadZone = document.getElementById('uploadZone');
        const csvFileInput = document.getElementById('csvFileInput');
        const analysisResults = document.getElementById('analysisResults');

        uploadZone.addEventListener('click', () => csvFileInput.click());

        uploadZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadZone.classList.add('dragover');
        });

        uploadZone.addEventListener('dragleave', () => {
            uploadZone.classList.remove('dragover');
        });

        uploadZone.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadZone.classList.remove('dragover');
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                csvFileInput.files = files;
                handleCSVUpload({ target: { files: files } });
            }
        });

        csvFileInput.addEventListener('change', handleCSVUpload);

        async function handleCSVUpload(event) {
            const file = event.target.files[0];
            if (!file) return;

            const formData = new FormData();
            formData.append('csv_file', file);

            try {
                const response = await fetch('process_bulk_mail.php?action=upload', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    document.getElementById('analysisFileName').textContent = file.name;
                    document.getElementById('totalRecords').textContent = data.total_records;
                    document.getElementById('validEmails').textContent = data.valid_emails;

                    // Display column mapping
                    const mappingContainer = document.getElementById('columnMapping');
                    mappingContainer.innerHTML = data.columns.map(col => `
                        <div class="mapping-row">
                            <div class="mapping-label">${col}</div>
                            <div style="color: var(--apple-gray); font-size: 13px;">{${col}}</div>
                        </div>
                    `).join('');

                    analysisResults.classList.add('active');
                    showAlert('success', `CSV loaded: ${data.total_records} records, ${data.valid_emails} valid emails`);
                } else {
                    showAlert('error', data.error || 'Failed to process CSV');
                }
            } catch (error) {
                console.error('Error:', error);
                showAlert('error', 'Failed to upload CSV: ' + error.message);
            }
        }

        // ========== DRIVE FILES ==========
        let selectedFileId = null;

        async function loadDriveFiles() {
            const container = document.getElementById('driveFilesList');

            try {
                const response = await fetch('fetch_drive_files.php');
                const data = await response.json();

                if (data.success && data.files && data.files.length > 0) {
                    container.innerHTML = data.files.map(file => `
                        <div class="drive-file-item" data-file-id="${file.id}" data-file-name="${file.name}" data-file-size="${file.size}">
                            <div class="drive-file-icon">📄</div>
                            <div class="drive-file-info">
                                <div class="drive-file-name">${file.name}</div>
                                <div class="drive-file-size">${formatBytes(file.size)}</div>
                            </div>
                            <div class="drive-file-check">
                                <span class="material-icons" style="display: none;">check</span>
                            </div>
                        </div>
                    `).join('');

                    // Add click handlers
                    document.querySelectorAll('.drive-file-item').forEach(item => {
                        item.addEventListener('click', () => selectDriveFile(item));
                    });
                } else {
                    container.innerHTML = `
                        <div class="empty-state">
                            <div class="empty-state-icon">📁</div>
                            <p>No files found in Drive</p>
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Error loading Drive files:', error);
                container.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-state-icon">❌</div>
                        <p>Failed to load files</p>
                    </div>
                `;
            }
        }

        function selectDriveFile(item) {
            // Deselect all
            document.querySelectorAll('.drive-file-item').forEach(el => {
                el.classList.remove('selected');
                el.querySelector('.material-icons').style.display = 'none';
            });

            // Select this one
            item.classList.add('selected');
            item.querySelector('.material-icons').style.display = 'block';

            // Store selection
            selectedFileId = item.dataset.fileId;

            // Show selected attachment
            document.getElementById('selectedFileName').textContent = item.dataset.fileName;
            document.getElementById('selectedFileSize').textContent = formatBytes(parseInt(item.dataset.fileSize));
            document.getElementById('selectedAttachment').classList.add('active');
        }

        document.getElementById('clearAttachmentBtn').addEventListener('click', () => {
            document.querySelectorAll('.drive-file-item').forEach(el => {
                el.classList.remove('selected');
                el.querySelector('.material-icons').style.display = 'none';
            });
            selectedFileId = null;
            document.getElementById('selectedAttachment').classList.remove('active');
        });

        // ========== ADD TO QUEUE ==========
        document.getElementById('addToQueueBtn').addEventListener('click', addToQueue);

        async function addToQueue() {
            const subject = document.getElementById('subject').value;
            const message = document.getElementById('message').value;

            if (!subject || !message) {
                showAlert('error', 'Please fill in subject and message');
                return;
            }

            const btn = document.getElementById('addToQueueBtn');
            btn.disabled = true;
            btn.innerHTML = '<span class="material-icons">hourglass_empty</span> Adding...';

            try {
                const response = await fetch('process_bulk_mail.php?action=add_to_queue', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        subject: subject,
                        message: message,
                        attachment_file_id: selectedFileId
                    })
                });

                const data = await response.json();

                if (data.success) {
                    showAlert('success', `Added ${data.added_count} emails to queue`);
                    await loadQueue();
                    
                    // Reset form
                    document.getElementById('subject').value = '';
                    document.getElementById('message').value = '';
                } else {
                    showAlert('error', data.error || 'Failed to add emails to queue');
                }
            } catch (error) {
                console.error('Error:', error);
                showAlert('error', 'Failed to add emails to queue: ' + error.message);
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<span class="material-icons">add_circle</span> Add to Queue';
            }
        }

        // ========== QUEUE MANAGEMENT ==========
        async function loadQueue() {
            try {
                const response = await fetch('process_bulk_mail.php?action=status');
                const data = await response.json();

                if (data.success) {
                    document.getElementById('stat-pending').textContent = data.pending || 0;
                    document.getElementById('stat-completed').textContent = data.completed || 0;
                    document.getElementById('stat-failed').textContent = data.failed || 0;

                    await loadQueueList();
                }
            } catch (error) {
                console.error('Error loading queue:', error);
            }
        }

        async function loadQueueList() {
            const container = document.getElementById('queueTableContainer');

            try {
                const response = await fetch('process_bulk_mail.php?action=queue_list');
                const data = await response.json();

                if (data.success) {
                    if (data.queue && data.queue.length > 0) {
                        container.innerHTML = `
                            <table class="queue-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Recipient</th>
                                        <th>Subject</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <th>Completed</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${data.queue.map(item => `
                                        <tr>
                                            <td>#${item.id}</td>
                                            <td>
                                                <strong>${item.recipient_name || 'N/A'}</strong><br>
                                                <span style="font-size: 12px; color: #666;">${item.recipient_email}</span>
                                            </td>
                                            <td>${item.subject || 'No Subject'}</td>
                                            <td>
                                                <span class="status-badge status-${item.status}">
                                                    ${item.status}
                                                </span>
                                            </td>
                                            <td style="font-size: 12px;">${formatDate(item.created_at)}</td>
                                            <td style="font-size: 12px;">${item.completed_at ? formatDate(item.completed_at) : '-'}</td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        `;
                    } else {
                        container.innerHTML = `
                            <div class="empty-state">
                                <div class="empty-state-icon">📭</div>
                                <h3>No emails in queue</h3>
                                <p>Upload a CSV file to get started</p>
                            </div>
                        `;
                    }
                }
            } catch (error) {
                console.error('Error loading queue list:', error);
            }
        }

        document.getElementById('processQueueBtn').addEventListener('click', processQueue);

        async function processQueue() {
            const btn = document.getElementById('processQueueBtn');
            const progressContainer = document.getElementById('processingProgress');
            const progressFill = document.getElementById('progressFill');
            const progressText = document.getElementById('progressText');

            btn.disabled = true;
            progressContainer.classList.add('active');

            const pending = parseInt(document.getElementById('stat-pending').textContent);

            if (pending === 0) {
                showAlert('error', 'No pending emails to process');
                btn.disabled = false;
                progressContainer.classList.remove('active');
                return;
            }

            let processed = 0;
            let success = 0;
            let failed = 0;

            for (let i = 0; i < pending; i++) {
                try {
                    const response = await fetch('process_bulk_mail.php?action=process', {
                        method: 'POST'
                    });

                    const data = await response.json();

                    if (data.success) {
                        if (data.email_sent) {
                            success++;
                        } else {
                            failed++;
                        }
                        processed++;
                        const percent = (processed / pending) * 100;
                        progressFill.style.width = percent + '%';
                        progressText.textContent = `Processing ${processed}/${pending} emails (${success} sent, ${failed} failed)...`;

                        await loadQueue();
                        await new Promise(resolve => setTimeout(resolve, 500));
                    } else {
                        failed++;
                        processed++;
                    }
                } catch (error) {
                    console.error('Error:', error);
                    failed++;
                    processed++;
                }
            }

            progressText.textContent = `Completed! Processed ${processed} emails (${success} sent, ${failed} failed).`;
            btn.disabled = false;

            setTimeout(() => {
                progressContainer.classList.remove('active');
                progressFill.style.width = '0%';
            }, 3000);

            if (failed > 0) {
                showAlert('info', `Processed ${processed} emails: ${success} sent successfully, ${failed} failed`);
            } else {
                showAlert('success', `Successfully sent all ${success} emails!`);
            }
        }

        async function clearQueue() {
            if (!confirm('Are you sure you want to clear all pending emails?')) {
                return;
            }

            try {
                const response = await fetch('process_bulk_mail.php?action=clear', {
                    method: 'POST'
                });

                const data = await response.json();

                if (data.success) {
                    showAlert('success', data.message);
                    loadQueue();
                } else {
                    showAlert('error', data.error);
                }
            } catch (error) {
                showAlert('error', 'Failed to clear queue');
            }
        }

        // ========== UTILITIES ==========
        function formatDate(dateString) {
            if (!dateString) return 'N/A';
            const date = new Date(dateString);
            return date.toLocaleString();
        }

        function formatBytes(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        }

        function showAlert(type, message) {
            const container = document.querySelector('.container');
            const alert = document.createElement('div');
            alert.className = `alert alert-${type}`;

            const iconMap = {
                'success': 'check_circle',
                'error': 'error',
                'info': 'info'
            };

            alert.innerHTML = `
                <span class="material-icons">${iconMap[type]}</span>
                <div>${message}</div>
            `;

            container.insertBefore(alert, container.firstChild);

            setTimeout(() => {
                alert.remove();
            }, 5000);
        }

        // ========== INITIALIZATION ==========
        document.addEventListener('DOMContentLoaded', function() {
            loadQueue();
            loadDriveFiles();
        });
    </script>
</body>

</html>