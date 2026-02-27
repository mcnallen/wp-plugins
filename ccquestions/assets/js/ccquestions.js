/**
 * CreatorConnected Q&A — Frontend JS
 * No dependencies required.
 */
( () => {
  'use strict';

  const cfg = window.CC_QA || {};
  const { ajax_url, nonce, logged_in, login_url, strings } = cfg;

  /* ─────────────────────────────────────
     Utility helpers
  ───────────────────────────────────── */
  const $ = ( sel, ctx = document ) => ctx.querySelector( sel );
  const $$ = ( sel, ctx = document ) => [ ...ctx.querySelectorAll( sel ) ];

  function toast( msg, type = 'default', duration = 3500 ) {
    const el = document.getElementById( 'qa-toast' );
    if ( ! el ) return;
    el.textContent = msg;
    el.className   = 'qa-toast qa-toast-enter' + ( type !== 'default' ? ' qa-toast-' + type : '' );
    el.hidden      = false;
    const clear = () => {
      el.classList.remove( 'qa-toast-enter' );
      el.classList.add( 'qa-toast-exit' );
      setTimeout( () => { el.hidden = true; el.className = 'qa-toast'; }, 280 );
    };
    const timer = setTimeout( clear, duration );
    el.onclick = () => { clearTimeout( timer ); clear(); };
  }

  async function ajax( action, data = {} ) {
    const body = new URLSearchParams( { action, nonce, ...data } );
    const res  = await fetch( ajax_url, { method: 'POST', body } );
    return res.json();
  }

  function setLoading( btn, loading ) {
    if ( loading ) {
      btn.dataset.originalText = btn.innerHTML;
      btn.innerHTML = '<span class="qa-spinner"></span>' + ( strings.submitting || 'Submitting…' );
      btn.disabled  = true;
    } else {
      btn.innerHTML = btn.dataset.originalText || btn.innerHTML;
      btn.disabled  = false;
    }
  }

  /* ─────────────────────────────────────
     Ask Question Form
  ───────────────────────────────────── */
  function initAskForm() {
    const trigger   = document.getElementById( 'qa-ask-trigger' );
    const form      = document.getElementById( 'qa-ask-form' );
    const closeBtn  = document.getElementById( 'qa-ask-close' );
    const cancelBtn = document.getElementById( 'qa-cancel-question' );
    const submitBtn = document.getElementById( 'qa-submit-question' );
    const askBtn    = $( '.btn-qa-ask' );
    const titleInput = document.getElementById( 'qa-question-title' );
    const countEl   = document.getElementById( 'qa-title-count' );

    if ( ! form ) return;

    const open  = () => { form.hidden = false; trigger && ( trigger.style.display = 'none' ); titleInput && titleInput.focus(); };
    const close = () => { form.hidden = true;  trigger && ( trigger.style.display = '' ); };

    trigger   && trigger.addEventListener( 'click', open );
    askBtn    && askBtn.addEventListener( 'click', open );
    closeBtn  && closeBtn.addEventListener( 'click', close );
    cancelBtn && cancelBtn.addEventListener( 'click', close );

    if ( titleInput && countEl ) {
      const titleMax = cfg.title_max || 200;
      titleInput.setAttribute( 'maxlength', titleMax );
      titleInput.addEventListener( 'input', () => {
        const len = titleInput.value.length;
        countEl.textContent = len + ' / ' + titleMax;
        countEl.style.color = len > titleMax - 20 ? 'var(--orange)' : '';
      } );
    }

    $$( '.qa-topic-pill', form ).forEach( pill => {
      pill.addEventListener( 'click', () => pill.classList.toggle( 'active' ) );
    } );

    if ( submitBtn ) {
      submitBtn.addEventListener( 'click', async () => {
        if ( ! logged_in ) { toast( strings.login_to_ask, 'error' ); return; }

        const title   = ( document.getElementById( 'qa-question-title' )?.value || '' ).trim();
        const content = ( document.getElementById( 'qa-question-body' )?.value || '' ).trim();
        const topics  = $$( '.qa-topic-pill.active', form ).map( p => p.dataset.value );

        if ( title.length < 10 ) {
          toast( 'Question must be at least 10 characters.', 'error' );
          document.getElementById( 'qa-question-title' )?.focus();
          return;
        }

        setLoading( submitBtn, true );
        try {
          const res = await ajax( 'cc_qa_submit_question', { title, content, 'topics[]': topics } );
          if ( res.success ) {
            toast( res.data.message, 'success' );
            if ( res.data.question_url ) {
              window.location.href = res.data.question_url;
            } else {
              close();
            }
          } else {
            toast( res.data?.message || strings.error, 'error' );
          }
        } catch {
          toast( strings.error, 'error' );
        } finally {
          setLoading( submitBtn, false );
        }
      } );
    }
  }

  /* ─────────────────────────────────────
     Submit Answer
  ───────────────────────────────────── */
  function initAnswerForm() {
    const submitBtn = document.getElementById( 'qa-submit-answer' );
    if ( ! submitBtn ) return;

    submitBtn.addEventListener( 'click', async () => {
      if ( ! logged_in ) { toast( strings.login_to_answer, 'error' ); return; }

      const questionId = submitBtn.dataset.question;
      const content    = ( document.getElementById( 'qa-answer-body' )?.value || '' ).trim();

      if ( content.length < 20 ) {
        toast( 'Your answer must be at least 20 characters.', 'error' );
        document.getElementById( 'qa-answer-body' )?.focus();
        return;
      }

      setLoading( submitBtn, true );
      try {
        const res = await ajax( 'cc_qa_submit_answer', { question_id: questionId, content } );
        if ( res.success ) {
          toast( res.data.message, 'success' );
          const list = document.getElementById( `answers-list-${questionId}` );
          if ( list && res.data.html ) {
            const noAns = $( '.qa-no-answers', list );
            if ( noAns ) noAns.remove();
            list.insertAdjacentHTML( 'beforeend', res.data.html );
          }
          const countEl = document.getElementById( `answer-count-${questionId}` );
          if ( countEl ) countEl.textContent = res.data.answer_count;
          const textarea = document.getElementById( 'qa-answer-body' );
          if ( textarea ) textarea.value = '';
          initVotes();
          initAccept();
          initDelete();
          initReplies();
        } else {
          toast( res.data?.message || strings.error, 'error' );
        }
      } catch {
        toast( strings.error, 'error' );
      } finally {
        setLoading( submitBtn, false );
      }
    } );
  }

  /* ─────────────────────────────────────
     Voting
  ───────────────────────────────────── */
  function initVotes() {
    $$( '.qa-vote-btn:not([data-vote-bound])' ).forEach( btn => {
      btn.dataset.voteBound = '1';
      btn.addEventListener( 'click', async () => {
        if ( ! logged_in ) { toast( strings.login_to_vote, 'error' ); return; }
        if ( btn.disabled ) return;
        const postId   = btn.dataset.postId;
        const voteType = btn.dataset.vote || '1';
        try {
          const res = await ajax( 'cc_qa_vote', { post_id: postId, vote_type: voteType } );
          if ( res.success ) {
            toast( strings.vote_thanks, 'success' );
            $$( `#votes-${postId}` ).forEach( el => ( el.textContent = res.data.count ) );
            const upBtn = $( `.qa-vote-up[data-post-id="${postId}"]` );
            if ( upBtn && voteType === '1' ) upBtn.classList.add( 'voted' );
            $$( `.qa-vote-btn[data-post-id="${postId}"]` ).forEach( b => ( b.disabled = true ) );
          } else {
            toast( res.data?.message || strings.already_voted, 'error' );
          }
        } catch {
          toast( strings.error, 'error' );
        }
      } );
    } );
  }

  /* ─────────────────────────────────────
     Accept Answer
  ───────────────────────────────────── */
  function initAccept() {
    $$( '.qa-accept-btn:not([data-accept-bound])' ).forEach( btn => {
      btn.dataset.acceptBound = '1';
      btn.addEventListener( 'click', async () => {
        const answerId = btn.dataset.answerId;
        setLoading( btn, true );
        try {
          const res = await ajax( 'cc_qa_accept_answer', { answer_id: answerId } );
          if ( res.success ) {
            toast( 'Answer accepted!', 'success' );
            $$( '.qa-answer-accepted' ).forEach( card => {
              card.classList.remove( 'qa-answer-accepted' );
              $( '.qa-accepted-banner', card )?.remove();
              $( '.qa-accepted-label', card )?.remove();
            } );
            const card = document.getElementById( `answer-${answerId}` );
            if ( card ) {
              card.classList.add( 'qa-answer-accepted' );
              card.insertAdjacentHTML( 'afterbegin', '<div class="qa-accepted-banner">✓ Accepted Answer</div>' );
              btn.outerHTML = '<span class="qa-accepted-label">✓ Accepted</span>';
            }
          } else {
            toast( res.data?.message || strings.error, 'error' );
          }
        } catch {
          toast( strings.error, 'error' );
        }
      } );
    } );
  }

  /* ─────────────────────────────────────
     Delete Question / Answer
  ───────────────────────────────────── */
  function initDelete() {
    $$( '.qa-delete-btn:not([data-delete-bound])' ).forEach( btn => {
      btn.dataset.deleteBound = '1';
      btn.addEventListener( 'click', async () => {
        if ( ! confirm( strings.confirm_delete ) ) return;
        const action   = btn.dataset.action;
        const postId   = btn.dataset.postId;
        const answerId = btn.dataset.answerId;
        try {
          let res;
          if ( action === 'delete_question' ) {
            res = await ajax( 'cc_qa_delete_question', { post_id: postId } );
            if ( res.success ) {
              toast( 'Question deleted.', 'success' );
              const redirect = btn.dataset.redirect;
              if ( redirect ) {
                setTimeout( () => { window.location.href = redirect; }, 800 );
              } else {
                document.getElementById( `question-${postId}` )?.remove();
              }
            }
          } else if ( action === 'delete_answer' ) {
            res = await ajax( 'cc_qa_delete_answer', { answer_id: answerId } );
            if ( res.success ) {
              document.getElementById( `answer-${answerId}` )?.remove();
              toast( 'Answer deleted.', 'success' );
              const questionId = res.data?.question_id;
              if ( questionId ) {
                const countEl = document.getElementById( `answer-count-${questionId}` );
                if ( countEl ) countEl.textContent = res.data.answer_count;
              }
            }
          }
          if ( res && ! res.success ) {
            toast( res.data?.message || strings.error, 'error' );
          }
        } catch {
          toast( strings.error, 'error' );
        }
      } );
    } );
  }

  /* ─────────────────────────────────────
     Sort Tabs (question list)
  ───────────────────────────────────── */
  function initSortTabs() {
    const feed    = document.getElementById( 'qa-question-feed' );
    const loadBtn = document.getElementById( 'qa-load-more' );
    if ( ! feed ) return;

    let currentSort   = 'newest';
    let currentTopic  = '';
    let currentSearch = '';

    async function reloadFeed() {
      feed.style.opacity = '0.5';
      try {
        const res = await ajax( 'cc_qa_load_more_questions', {
          page: 1, sort: currentSort, topic: currentTopic, search: currentSearch,
        } );
        if ( res.success ) {
          feed.innerHTML = res.data.html || '<div class="qa-empty"><span class="qa-empty-icon">💬</span><p>No questions found.</p></div>';
          if ( loadBtn ) {
            loadBtn.dataset.page = 1;
            document.getElementById( 'qa-load-more-wrap' ).style.display = res.data.has_more ? '' : 'none';
          }
          initVotes();
          initDelete();
        }
      } catch {
        toast( strings.error, 'error' );
      } finally {
        feed.style.opacity = '1';
      }
    }

    $$( '.qa-sort-tabs .qa-sort-tab' ).forEach( tab => {
      tab.addEventListener( 'click', () => {
        $$( '.qa-sort-tabs .qa-sort-tab' ).forEach( t => t.classList.remove( 'active' ) );
        tab.classList.add( 'active' );
        currentSort = tab.dataset.sort;
        reloadFeed();
      } );
    } );

    // Hero "Unanswered" stat link — activate the Unanswered tab and reload
    document.addEventListener( 'click', e => {
      const link = e.target.closest( '[data-filter-unanswered]' );
      if ( ! link || ! document.getElementById( 'qa-question-feed' ) ) return;
      e.preventDefault();
      currentSort = 'unanswered';
      $$( '.qa-sort-tabs .qa-sort-tab' ).forEach( t => {
        t.classList.toggle( 'active', t.dataset.sort === 'unanswered' );
      } );
      reloadFeed();
      // Smooth scroll to the feed after a brief tick so DOM updates first
      setTimeout( () => {
        document.getElementById( 'qa-question-feed' )
          ?.scrollIntoView( { behavior: 'smooth', block: 'start' } );
      }, 80 );
    } );

    $$( '.qa-topic-filter' ).forEach( btn => {
      btn.addEventListener( 'click', () => {
        $$( '.qa-topic-filter' ).forEach( b => b.classList.remove( 'active' ) );
        btn.classList.add( 'active' );
        currentTopic = btn.dataset.topic;
        reloadFeed();
      } );
    } );

    // Topic badge buttons inside question cards — use event delegation so
    // dynamically-loaded cards are covered. Clicking a card tag activates the
    // matching filter pill in the filter bar and reloads the feed.
    document.addEventListener( 'click', e => {
      const btn = e.target.closest( '.qa-topic-filter-link' );
      if ( ! btn || ! document.getElementById( 'qa-question-feed' ) ) return;
      const topic = btn.dataset.topic || '';
      currentTopic = topic;
      // Highlight the matching pill in the filter bar (if it exists)
      $$( '.qa-topic-filter' ).forEach( b => {
        b.classList.toggle( 'active', b.dataset.topic === topic );
      } );
      reloadFeed();
      // Scroll filter bar into view so the user can see it's active
      const bar = document.querySelector( '.qa-topic-filter-bar' );
      if ( bar ) bar.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
    } );

    const searchInput = document.getElementById( 'qa-search' );
    if ( searchInput ) {
      let timer;
      searchInput.addEventListener( 'input', () => {
        clearTimeout( timer );
        timer = setTimeout( () => { currentSearch = searchInput.value.trim(); reloadFeed(); }, 420 );
      } );
    }

    if ( loadBtn ) {
      loadBtn.addEventListener( 'click', async () => {
        const nextPage = parseInt( loadBtn.dataset.page, 10 ) + 1;
        const maxPages = parseInt( loadBtn.dataset.max,  10 );
        setLoading( loadBtn, true );
        try {
          const res = await ajax( 'cc_qa_load_more_questions', {
            page: nextPage, sort: currentSort, topic: currentTopic, search: currentSearch,
          } );
          if ( res.success ) {
            feed.insertAdjacentHTML( 'beforeend', res.data.html );
            loadBtn.dataset.page = nextPage;
            if ( ! res.data.has_more || nextPage >= maxPages ) {
              document.getElementById( 'qa-load-more-wrap' ).style.display = 'none';
            }
            initVotes();
            initDelete();
          }
        } catch {
          toast( strings.error, 'error' );
        } finally {
          setLoading( loadBtn, false );
        }
      } );
    }
  }

  /* ─────────────────────────────────────
     Answer Sort (single view)
  ───────────────────────────────────── */
  function initAnswerSort() {
    $$( '[data-answer-sort]' ).forEach( btn => {
      btn.addEventListener( 'click', async () => {
        const sort       = btn.dataset.answerSort;
        const questionId = btn.dataset.question;
        const list       = document.getElementById( `answers-list-${questionId}` );
        if ( ! list ) return;
        $$( '[data-answer-sort]' ).forEach( b => b.classList.remove( 'active' ) );
        btn.classList.add( 'active' );
        list.style.opacity = '0.5';
        try {
          const res = await ajax( 'cc_qa_load_more_answers', { question_id: questionId, page: 1, sort } );
          if ( res.success ) {
            list.innerHTML = res.data.html;
            // Reset load-more button state and sync sort
            const lmBtn = document.getElementById( 'qa-load-more-answers' );
            if ( lmBtn ) {
              lmBtn.dataset.page = 1;
              lmBtn.dataset.sort = sort;
              const wrap = document.getElementById( 'qa-load-more-answers-wrap' );
              if ( wrap ) wrap.style.display = res.data.has_more ? '' : 'none';
            }
            initVotes();
            initAccept();
            initDelete();
            initReplies();
          }
        } catch {
          toast( strings.error, 'error' );
        } finally {
          list.style.opacity = '1';
        }
      } );
    } );
  }

  /* ─────────────────────────────────────
     Load More Answers (single view)
  ───────────────────────────────────── */
  function initLoadMoreAnswers() {
    const loadBtn = document.getElementById( 'qa-load-more-answers' );
    if ( ! loadBtn ) return;

    loadBtn.addEventListener( 'click', async () => {
      const questionId = loadBtn.dataset.question;
      const nextPage   = parseInt( loadBtn.dataset.page, 10 ) + 1;
      const maxPages   = parseInt( loadBtn.dataset.max,  10 );
      const sort       = loadBtn.dataset.sort || 'votes';
      const list       = document.getElementById( `answers-list-${questionId}` );
      if ( ! list ) return;

      setLoading( loadBtn, true );
      try {
        const res = await ajax( 'cc_qa_load_more_answers', { question_id: questionId, page: nextPage, sort } );
        if ( res.success ) {
          list.insertAdjacentHTML( 'beforeend', res.data.html );
          loadBtn.dataset.page = nextPage;
          if ( ! res.data.has_more || nextPage >= maxPages ) {
            document.getElementById( 'qa-load-more-answers-wrap' ).style.display = 'none';
          }
          initVotes();
          initAccept();
          initDelete();
          initReplies();
        }
      } catch {
        toast( strings.error, 'error' );
      } finally {
        setLoading( loadBtn, false );
      }
    } );
  }

  /* ─────────────────────────────────────
     Reply System

     Uses event delegation on document so it works for dynamically added
     answer cards without needing to be called again after DOM updates.
     A single guard flag prevents double-registration if called more than once.
  ───────────────────────────────────── */
  let repliesInited = false;
  function initReplies() {
    if ( repliesInited ) return;
    repliesInited = true;

    // Toggle reply form open/closed
    document.addEventListener( 'click', e => {
      const btn = e.target.closest( '.qa-reply-toggle' );
      if ( ! btn ) return;
      if ( ! logged_in ) { toast( strings.login_to_answer, 'error' ); return; }
      const answerId = btn.dataset.answerId;
      const form     = document.getElementById( `reply-form-${answerId}` );
      if ( ! form ) return;
      const isOpen = ! form.hidden;
      form.hidden  = isOpen;
      btn.classList.toggle( 'active', ! isOpen );
      if ( ! isOpen ) form.querySelector( '.qa-reply-input' )?.focus();
    } );

    // Cancel reply
    document.addEventListener( 'click', e => {
      const btn = e.target.closest( '.btn-qa-reply-cancel' );
      if ( ! btn ) return;
      const answerId = btn.dataset.answerId;
      const form     = document.getElementById( `reply-form-${answerId}` );
      if ( form ) form.hidden = true;
      document.querySelector( `.qa-reply-toggle[data-answer-id="${answerId}"]` )?.classList.remove( 'active' );
    } );

    // Submit reply
    document.addEventListener( 'click', async e => {
      const btn = e.target.closest( '.btn-qa-reply-submit' );
      if ( ! btn ) return;
      if ( ! logged_in ) { toast( strings.login_to_answer, 'error' ); return; }
      const answerId = btn.dataset.answerId;
      const textarea = document.querySelector( `.qa-reply-input[data-answer-id="${answerId}"]` );
      const content  = textarea?.value.trim();
      if ( ! content || content.length < 2 ) { toast( 'Reply is too short.', 'error' ); textarea?.focus(); return; }
      setLoading( btn, true );
      try {
        const res = await ajax( 'cc_qa_submit_reply', { answer_id: answerId, content } );
        if ( res.success ) {
          const section = document.getElementById( `replies-${answerId}` );
          let list = section.querySelector( '.qa-replies-list' );
          if ( ! list ) {
            list = document.createElement( 'div' );
            list.className = 'qa-replies-list';
            section.insertBefore( list, section.querySelector( '.qa-reply-form' ) );
          }
          list.insertAdjacentHTML( 'beforeend', res.data.html );
          const toggle = document.querySelector( `.qa-reply-toggle[data-answer-id="${answerId}"]` );
          if ( toggle ) {
            let badge = toggle.querySelector( '.qa-reply-count' );
            if ( ! badge ) { badge = document.createElement( 'span' ); badge.className = 'qa-reply-count'; toggle.appendChild( badge ); }
            badge.textContent = ( parseInt( badge.textContent ) || 0 ) + 1;
          }
          textarea.value = '';
          document.getElementById( `reply-form-${answerId}` ).hidden = true;
          toggle?.classList.remove( 'active' );
          toast( 'Reply posted!', 'success' );
          // initDeleteReply uses delegation too — no re-call needed
        } else {
          toast( res.data?.message || strings.error, 'error' );
        }
      } catch {
        toast( strings.error, 'error' );
      } finally {
        setLoading( btn, false );
      }
    } );
  }

  /* ─────────────────────────────────────
     Delete Reply — delegation with guard
  ───────────────────────────────────── */
  let deleteReplyInited = false;
  function initDeleteReply() {
    if ( deleteReplyInited ) return;
    deleteReplyInited = true;

    document.addEventListener( 'click', async e => {
      const btn = e.target.closest( '.qa-delete-reply-btn' );
      if ( ! btn ) return;
      if ( ! confirm( strings.confirm_delete ) ) return;
      const replyId  = btn.dataset.replyId;
      const answerId = btn.dataset.answerId;
      try {
        const res = await ajax( 'cc_qa_delete_reply', { reply_id: replyId, answer_id: answerId } );
        if ( res.success ) {
          document.getElementById( `reply-${replyId}` )?.remove();
          const toggle = document.querySelector( `.qa-reply-toggle[data-answer-id="${answerId}"]` );
          const badge  = toggle?.querySelector( '.qa-reply-count' );
          if ( badge ) {
            const n = Math.max( 0, parseInt( badge.textContent ) - 1 );
            if ( n === 0 ) badge.remove(); else badge.textContent = n;
          }
          toast( 'Reply deleted.', '' );
        } else {
          toast( res.data?.message || strings.error, 'error' );
        }
      } catch {
        toast( strings.error, 'error' );
      }
    } );
  }

  /* ─────────────────────────────────────
     Deep-link scroll to answer
  ───────────────────────────────────── */
  function handleDeepLink() {
    const hash = window.location.hash;
    if ( hash && hash.startsWith( '#answer-' ) ) {
      setTimeout( () => {
        const el = document.querySelector( hash );
        if ( el ) el.scrollIntoView( { behavior: 'smooth', block: 'center' } );
      }, 300 );
    }
  }

  /* ─────────────────────────────────────
     Edit Question / Answer — inline form
  ───────────────────────────────────── */
  function initEdit() {
    document.addEventListener( 'click', e => {

      // ── Open ──
      const editBtn = e.target.closest( '.qa-edit-btn' );
      if ( editBtn ) {
        const postId = editBtn.dataset.postId;
        const type   = editBtn.dataset.type;
        const form   = document.getElementById( `edit-${type}-${postId}` );
        if ( ! form ) return;

        // Single question page uses .qa-detail-editable wrapper.
        // Card view uses .qa-display-content wrapper.
        // Toggle whichever wrapper exists.
        const article = form.closest( 'article, .qa-answer-card, .qa-question-card' );
        if ( article ) {
          article.querySelectorAll( '.qa-detail-editable, .qa-display-content' )
            .forEach( el => el.hidden = true );
        }
        form.hidden = false;
        ( form.querySelector( '.qa-edit-title' ) || form.querySelector( 'textarea' ) )?.focus();
        return;
      }

      // ── Cancel ──
      const cancelBtn = e.target.closest( '.qa-cancel-edit-btn' );
      if ( cancelBtn ) {
        const postId = cancelBtn.dataset.postId;
        const type   = cancelBtn.dataset.type;
        const form   = document.getElementById( `edit-${type}-${postId}` );
        if ( ! form ) return;
        form.hidden = true;
        const article = form.closest( 'article, .qa-answer-card, .qa-question-card' );
        if ( article ) {
          article.querySelectorAll( '.qa-detail-editable, .qa-display-content' )
            .forEach( el => el.hidden = false );
        }
        return;
      }

      // ── Save ──
      const saveBtn = e.target.closest( '.qa-save-edit-btn' );
      if ( ! saveBtn ) return;

      const postId  = saveBtn.dataset.postId;
      const type    = saveBtn.dataset.type;
      const form    = document.getElementById( `edit-${type}-${postId}` );
      if ( ! form ) return;

      const title   = form.querySelector( '.qa-edit-title' )?.value.trim() ?? '';
      const content = form.querySelector( '.qa-edit-body' )?.value.trim() ?? '';

      if ( type === 'question' && ! title ) { toast( 'Title cannot be empty.', 'error' ); return; }
      if ( ! content )                      { toast( 'Content cannot be empty.', 'error' ); return; }

      setLoading( saveBtn, true );
      const action = type === 'question' ? 'cc_qa_edit_question' : 'cc_qa_edit_answer';
      const data   = type === 'question' ? { post_id: postId, title, content } : { post_id: postId, content };

      ajax( action, data ).then( res => {
        setLoading( saveBtn, false );
        if ( ! res.success ) { toast( res.data?.message || strings.error, 'error' ); return; }

        // Hide form, restore wrapper(s), update displayed text
        form.hidden = true;
        const article = form.closest( 'article, .qa-answer-card, .qa-question-card' );
        if ( article ) {
          // Update title (single page h1 or card link)
          if ( type === 'question' ) {
            const h = article.querySelector( '.qa-detail-title' );
            if ( h ) h.textContent = res.data.title;
            const link = article.querySelector( '.qa-card-link' );
            if ( link ) link.textContent = res.data.title;
          }
          // Update body (single page .qa-detail-body or card .qa-display-content)
          const body = article.querySelector( '.qa-detail-body, .qa-display-content' );
          if ( body ) {
            body.innerHTML = type === 'answer'
              ? res.data.content.replace( /\n/g, '<br>' )
              : ( res.data.excerpt ?? res.data.content );
          }
          // Also update card excerpt if present (question cards)
          if ( type === 'question' ) {
            const ex = article.querySelector( '.qa-card-excerpt' );
            if ( ex ) ex.textContent = res.data.excerpt ?? '';
          }
          // Restore wrappers
          article.querySelectorAll( '.qa-detail-editable, .qa-display-content' )
            .forEach( el => el.hidden = false );
        }
        toast( 'Saved!', 'success' );
      } ).catch( () => { setLoading( saveBtn, false ); toast( strings.error, 'error' ); } );
    } );
  }

  /* ─────────────────────────────────────
     Edit Reply — 1-hour inline form
  ───────────────────────────────────── */
  function initEditReply() {
    document.addEventListener( 'click', e => {

      // ── Open reply edit ──
      const editBtn = e.target.closest( '.qa-edit-reply-btn' );
      if ( editBtn ) {
        const replyId = editBtn.dataset.replyId;
        const content = document.getElementById( `reply-content-${replyId}` );
        const form    = document.getElementById( `reply-edit-${replyId}` );
        if ( content ) content.hidden = true;
        if ( form )    { form.hidden = false; form.querySelector( 'textarea' )?.focus(); }
        return;
      }

      // ── Cancel reply edit ──
      const cancelBtn = e.target.closest( '.qa-cancel-reply-edit-btn' );
      if ( cancelBtn ) {
        const replyId = cancelBtn.dataset.replyId;
        const content = document.getElementById( `reply-content-${replyId}` );
        const form    = document.getElementById( `reply-edit-${replyId}` );
        if ( form )    form.hidden = true;
        if ( content ) content.hidden = false;
        return;
      }

      // ── Save reply edit ──
      const saveBtn = e.target.closest( '.qa-save-reply-edit-btn' );
      if ( ! saveBtn ) return;

      const replyId  = saveBtn.dataset.replyId;
      const answerId = saveBtn.dataset.answerId;
      const form     = document.getElementById( `reply-edit-${replyId}` );
      if ( ! form ) return;

      const newContent = form.querySelector( 'textarea' )?.value.trim() ?? '';
      if ( ! newContent ) { toast( 'Reply cannot be empty.', 'error' ); return; }

      setLoading( saveBtn, true );
      ajax( 'cc_qa_edit_reply', { reply_id: replyId, answer_id: answerId, content: newContent } )
        .then( res => {
          setLoading( saveBtn, false );
          if ( ! res.success ) { toast( res.data?.message || strings.error, 'error' ); return; }
          form.hidden = true;
          const contentEl = document.getElementById( `reply-content-${replyId}` );
          if ( contentEl ) {
            contentEl.innerHTML = res.data.content.replace( /\n/g, '<br>' );
            contentEl.hidden = false;
          }
          toast( 'Reply updated.', 'success' );
        } )
        .catch( () => { setLoading( saveBtn, false ); toast( strings.error, 'error' ); } );
    } );
  }

  /* ─────────────────────────────────────
     Leaderboard Tab Switching
  ───────────────────────────────────── */
  function initLeaderboard() {
    document.addEventListener( 'click', e => {
      const tab = e.target.closest( '.qa-lb-tab' );
      if ( ! tab ) return;
      const board = tab.closest( '.cc-qa-leaderboard' );
      if ( ! board ) return;
      const tabId = tab.dataset.tab;
      board.querySelectorAll( '.qa-lb-tab' ).forEach( t => {
        t.classList.toggle( 'active', t === tab );
        t.setAttribute( 'aria-selected', t === tab ? 'true' : 'false' );
      } );
      board.querySelectorAll( '.qa-lb-panel' ).forEach( p => {
        p.classList.toggle( 'active', p.id === `lb-panel-${tabId}` );
      } );
    } );
  }

  /* ─────────────────────────────────────
     Profile Question Row — clickable card
     without nesting <a> inside <a>
  ───────────────────────────────────── */
  function initProfileRows() {
    document.addEventListener( 'click', e => {
      const row = e.target.closest( '.qa-profile-q-row[data-href]' );
      if ( ! row ) return;
      // If the click landed on or inside a real link, let it navigate naturally
      if ( e.target.closest( 'a' ) ) return;
      window.location.href = row.dataset.href;
    } );
  }

  /* ─────────────────────────────────────
     Init
  ───────────────────────────────────── */
  document.addEventListener( 'DOMContentLoaded', () => {
    initAskForm();
    initAnswerForm();
    initVotes();
    initAccept();
    initDelete();
    initEdit();
    initEditReply();
    initSortTabs();
    initAnswerSort();
    initLoadMoreAnswers();
    initReplies();
    initDeleteReply();
    initLeaderboard();
    initProfileRows();
    handleDeepLink();
  } );

} )();
