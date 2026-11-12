/**
 * Adds issue featured-image upload and board-card display.
 */
(function () {
  const { addFilter, addAction, removeAction, doAction } = wp.hooks;
  const { createElement, Fragment, useEffect, useRef, useState } = wp.element;
  const { __ } = wp.i18n;
  const { Button, CardMedia, Notice, Spinner } = wp.components;
  const actionName = 'alpaca.takeover.featuredImageChanged';
  const featuredImages = new Map();
  let cardInstance = 0;

  /**
   * Save or remove an issue's featured image.
   *
   * @param {number} issueId Issue ID.
   * @param {Object} options Request options.
   * @return {Promise<Object>} Updated image response.
   */
  const requestFeaturedImage = (issueId, options) =>
    wp.apiFetch({
      path: `/alpaca-takeover/v1/issues/${issueId}/featured-image`,
      headers: {
        'X-WP-Nonce': window.alpacaTakeoverFeaturedImage?.nonce || '',
        ...options.headers,
      },
      ...options,
    });

  /**
   * Keep the displayed image consistent across the drawer and board cards.
   *
   * @param {number}      issueId Issue ID.
   * @param {Object|null} image   Featured-image data.
   */
  const notifyFeaturedImageChanged = (issueId, image) => {
    featuredImages.set(String(issueId), image || null);
    doAction(actionName, issueId, image);
  };

  /**
   * Load featured images for the initial board data.
   *
   * @return {Promise<void>} Resolves when the image list has been loaded.
   */
  const loadBoardFeaturedImages = async () => {
    try {
      const response = await wp.apiFetch({
        path: '/alpaca-takeover/v1/featured-images',
      });

      Object.entries(response.images || {}).forEach(([issueId, image]) => {
        notifyFeaturedImageChanged(issueId, image);
      });
    } catch (error) {
      console.error('Failed to load issue featured images.', error);
    }
  };

  /**
   * Display a featured image in the media area of a board card.
   *
   * @param {Object} props               Component props.
   * @param {number} props.issueId       Issue ID.
   * @param {Object} props.featuredImage Image data.
   * @return {Element|null} Featured image or null.
   */
  const BoardFeaturedImage = ({ issueId, featuredImage }) => {
    const [image, setImage] = useState(() => {
      const key = String(issueId);
      return featuredImages.has(key)
        ? featuredImages.get(key)
        : featuredImage || null;
    });
    const namespace = useRef('');

    if (!namespace.current) {
      cardInstance += 1;
      namespace.current = `alpaca-takeover/card-${cardInstance}`;
    }

    useEffect(() => {
      const key = String(issueId);

      if (featuredImages.has(key)) {
        setImage(featuredImages.get(key));
        return;
      }

      setImage(featuredImage || null);

      if (featuredImage) {
        featuredImages.set(key, featuredImage);
      }
    }, [issueId, featuredImage]);

    useEffect(() => {
      const handleImageChange = (updatedIssueId, updatedImage) => {
        if (String(updatedIssueId) === String(issueId)) {
          setImage(updatedImage || null);
        }
      };

      addAction(actionName, namespace.current, handleImageChange);

      return () => removeAction(actionName, namespace.current);
    }, [issueId]);

    if (!image?.url) {
      return null;
    }

    return createElement(
      CardMedia,
      { className: 'alpaca-takeover-card-image' },
      createElement('img', {
        src: image.url,
        alt: image.alt || '',
        loading: 'lazy',
        decoding: 'async',
      }),
    );
  };

  /**
   * Render an image upload dropzone in the issue details drawer.
   *
   * @param {Object}             props                     Props provided by the Alpaca issue filter.
   * @param {number|undefined}   props.issueId             Issue ID.
   * @param {Object}             props.meta                Issue metadata.
   * @param {boolean}            props.isCreating          Whether the issue is being created.
   * @param {Function|undefined} props.registerAfterCreate Post-create callback registration.
   * @return {Element} Featured-image controls.
   */
  const FeaturedImageControl = ({
    issueId,
    meta,
    isCreating = false,
    registerAfterCreate,
  }) => {
    const initialImage = meta?.featured_image || null;
    const [image, setImage] = useState(initialImage);
    const [isBusy, setIsBusy] = useState(false);
    const [isDragging, setIsDragging] = useState(false);
    const [error, setError] = useState('');
    const [pendingUpload, setPendingUpload] = useState(null);
    const pendingUploadRef = useRef(pendingUpload);
    const fileInput = useRef(null);

    pendingUploadRef.current = pendingUpload;

    useEffect(() => {
      setImage(initialImage);
      setError('');
    }, [issueId, initialImage]);

    useEffect(() => {
      if (!pendingUpload?.previewUrl) {
        return undefined;
      }

      const { previewUrl } = pendingUpload;

      return () => window.URL.revokeObjectURL(previewUrl);
    }, [pendingUpload]);

    useEffect(() => {
      if (!isCreating || typeof registerAfterCreate !== 'function') {
        return undefined;
      }

      return registerAfterCreate(async (createdIssue) => {
        const upload = pendingUploadRef.current;

        if (!upload) {
          return;
        }

        const createdIssueId = Number(createdIssue?.id);

        if (!Number.isInteger(createdIssueId) || createdIssueId <= 0) {
          throw new Error(__('The featured image could not be saved. Please try again.', 'alpaca-takeover'));
        }

        setIsBusy(true);
        setError('');

        try {
          const formData = new FormData();
          formData.append('file', upload.file);

          const response = await requestFeaturedImage(createdIssueId, {
            method: 'POST',
            body: formData,
          });
          const savedImage = response.featured_image || null;

          if (!savedImage?.url) {
            throw new Error(__('The featured image could not be saved. Please try again.', 'alpaca-takeover'));
          }

          setImage(savedImage);
          setPendingUpload(null);
          notifyFeaturedImageChanged(createdIssueId, savedImage);
        } catch (requestError) {
          setError(
            requestError?.message ||
              __('The featured image could not be saved. Please try again.', 'alpaca-takeover'),
          );
          throw requestError;
        } finally {
          setIsBusy(false);
        }
      });
    }, [isCreating, registerAfterCreate]);

    const saveImage = async (options) => {
      setIsBusy(true);
      setError('');

      try {
        const response = await requestFeaturedImage(issueId, options);
        const savedImage = response.featured_image || null;
        setImage(savedImage);
        notifyFeaturedImageChanged(issueId, savedImage);
      } catch (requestError) {
        setError(
          requestError?.message || __('The featured image could not be saved. Please try again.', 'alpaca-takeover'),
        );
      } finally {
        setIsBusy(false);
      }
    };

    const handleFile = (file) => {
      if (!file) {
        return;
      }

      if (!file.type || !file.type.startsWith('image/')) {
        setError(__('Choose an image file to use as the featured image.', 'alpaca-takeover'));
        return;
      }

      if (isCreating) {
        const previewUrl = window.URL.createObjectURL(file);
        setPendingUpload({ file, previewUrl });
        setImage({ url: previewUrl, alt: '' });
        setError('');
        return;
      }

      const formData = new FormData();
      formData.append('file', file);

      saveImage({
        method: 'POST',
        body: formData,
      });
    };

    const handleDragEnter = (event) => {
      event.preventDefault();
      setIsDragging(true);
    };

    const handleDragLeave = (event) => {
      if (!event.currentTarget.contains(event.relatedTarget)) {
        setIsDragging(false);
      }
    };

    const handleDrop = (event) => {
      event.preventDefault();
      setIsDragging(false);
      handleFile(event.dataTransfer?.files?.[0]);
    };

    const openFileDialog = () => {
      fileInput.current?.click();
    };

    const removeImage = () => {
      if (isCreating) {
        setPendingUpload(null);
        setImage(null);
        setError('');
        return;
      }

      saveImage({ method: 'DELETE' });
    };

    return createElement(
      'section',
      {
        className: 'alpaca-takeover-featured-image',
        'aria-labelledby': `alpaca-takeover-featured-image-title-${issueId}`,
      },
      createElement(
        'div',
        {
          id: `alpaca-takeover-featured-image-title-${issueId}`,
          className: 'alpaca-details-grid__label',
        },
        __('Featured image', 'alpaca-takeover'),
      ),
      createElement('input', {
        ref: fileInput,
        className: 'alpaca-takeover-featured-image__input',
        type: 'file',
        accept: 'image/*',
        disabled: isBusy,
        onChange: (event) => {
          handleFile(event.currentTarget.files?.[0]);
          event.currentTarget.value = '';
        },
      }),
      image?.url
        ? createElement(
            'div',
            {
              className: `alpaca-takeover-featured-image__preview${isDragging ? ' is-dragging' : ''}`,
              onDragEnter: handleDragEnter,
              onDragOver: (event) => event.preventDefault(),
              onDragLeave: handleDragLeave,
              onDrop: handleDrop,
            },
            createElement('img', {
              src: image.url,
              alt: image.alt || '',
              decoding: 'async',
            }),
            createElement(
              'div',
              { className: 'alpaca-takeover-featured-image__actions' },
              createElement(
                Button,
                {
                  variant: 'secondary',
                  className: 'alpaca-takeover-featured-image__action',
                  onClick: openFileDialog,
                  disabled: isBusy,
                },
                __('Replace', 'alpaca-takeover'),
              ),
              createElement(
                Button,
                {
                  variant: 'secondary',
                  isDestructive: true,
                  className: 'alpaca-takeover-featured-image__action',
                  onClick: removeImage,
                  disabled: isBusy,
                },
                __('Remove', 'alpaca-takeover'),
              ),
            ),
          )
        : createElement(
            'div',
            {
              className: `alpaca-takeover-featured-image__dropzone${isDragging ? ' is-dragging' : ''}`,
              onDragEnter: handleDragEnter,
              onDragOver: (event) => event.preventDefault(),
              onDragLeave: handleDragLeave,
              onDrop: handleDrop,
            },
            createElement(
              'p',
              null,
              isDragging
                ? __('Drop to set the featured image for this issue.', 'alpaca-takeover')
                : __('Drop here to set the issue’s featured image, or choose a file.', 'alpaca-takeover'),
            ),
            createElement(
              Button,
              {
                variant: 'secondary',
                onClick: openFileDialog,
                disabled: isBusy,
              },
              __('Choose image', 'alpaca-takeover'),
            ),
          ),
      isBusy
        ? createElement(
            'p',
            { className: 'alpaca-takeover-featured-image__status', role: 'status' },
            createElement(Spinner),
            __('Saving featured image…', 'alpaca-takeover'),
          )
        : null,
      error
        ? createElement(
            Notice,
            {
              status: 'error',
              isDismissible: true,
              onRemove: () => setError(''),
            },
            error,
          )
        : null,
    );
  };

  addFilter(
    'alpaca.item.card.media',
    'alpaca-takeover/featured-image-card-media',
    (media, item) =>
      createElement(
        Fragment,
        null,
        media,
        createElement(BoardFeaturedImage, {
          issueId: item?.id,
          featuredImage: item?.meta?.featured_image,
        }),
      ),
  );

  addFilter(
    'alpaca.issue.belowmeta',
    'alpaca-takeover/featured-image-control',
    (content, context) =>
      createElement(
        Fragment,
        null,
        content,
        createElement(FeaturedImageControl, {
          issueId: context?.issueId,
          meta: context?.meta || {},
          isCreating: Boolean(context?.isCreating),
          registerAfterCreate: context?.registerAfterCreate,
        }),
      ),
  );

  loadBoardFeaturedImages();
})();
