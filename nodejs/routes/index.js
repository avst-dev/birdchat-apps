import express from 'express';

const router = express.Router();

// GET / - Home page
router.get('/', (req, res) => {
  if (req.session?.user_id) {
    return res.redirect('/chat');
  }
  
  res.render('index', {
    title: 'BIRDCHAT 🐦'
  });
});

export default router;
