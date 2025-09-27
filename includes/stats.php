<?php
// Statistics tracking functions

class StatsTracker {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    public function trackVisit() {
        $ip = getClientIP();
        $today = date('Y-m-d');
        
        // Update daily stats
        $stmt = $this->db->prepare("
            INSERT INTO site_stats (date, visits, unique_visitors, page_views) 
            VALUES (?, 1, 1, 1) 
            ON DUPLICATE KEY UPDATE 
            visits = visits + 1,
            page_views = page_views + 1
        ");
        $stmt->execute([$today]);
        
        // Track unique visitors using session
        if (!isset($_SESSION['visitor_tracked'])) {
            $_SESSION['visitor_tracked'] = true;
            
            // This is a unique visitor for today
            $stmt = $this->db->prepare("
                UPDATE site_stats 
                SET unique_visitors = unique_visitors + 1 
                WHERE date = ?
            ");
            $stmt->execute([$today]);
        }
    }
    
    public function trackPostView($post_id) {
        // Update post views
        $stmt = $this->db->prepare("UPDATE posts SET views = views + 1 WHERE id = ?");
        $stmt->execute([$post_id]);
        
        // Track in session to prevent multiple counts per session
        if (!isset($_SESSION['viewed_posts'])) {
            $_SESSION['viewed_posts'] = [];
        }
        
        if (!in_array($post_id, $_SESSION['viewed_posts'])) {
            $_SESSION['viewed_posts'][] = $post_id;
        }
    }
}
?>
