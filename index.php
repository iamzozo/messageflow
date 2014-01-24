<?php
if ( !is_user_logged_in() ) {
	wp_redirect( wp_login_url( site_url('/') ) );
	exit;
}
?>

<!DOCTYPE html>
<html>
        <head>
                <meta charset="UTF-8" />
                <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">

		<meta name="apple-mobile-web-app-capable" content="yes">
		<meta name="apple-mobile-web-app-status-bar-style" content="black">

		<script type="text/javascript" src="<?php echo get_template_directory_uri() . '/js/libs/jquery-1.10.2.min.js' ?>"></script>
		<script type="text/javascript" src="<?php echo get_template_directory_uri() . '/js/libs/datepicker.js' ?>"></script>
		<script type="text/javascript" src="<?php echo get_template_directory_uri() . '/js/libs/jquery-ui-1.10.3.custom.min.js' ?>"></script>
		<script type="text/javascript" src="<?php echo get_template_directory_uri() . '/js/libs/jquery.iframe-transport.js' ?>"></script>
		<script type="text/javascript" src="<?php echo get_template_directory_uri() . '/js/libs/jquery.fileupload.js' ?>"></script>
		<script type="text/javascript" src="<?php echo get_template_directory_uri() . '/js/libs/fullcalendar.min.js' ?>"></script>
		<script type="text/javascript" src="<?php echo get_template_directory_uri() . '/js/libs/advanced.js' ?>"></script>
		<script type="text/javascript" src="<?php echo get_template_directory_uri() . '/js/libs/wysihtml5-0.3.0.min.js' ?>"></script>
		<script type="text/javascript" src="<?php echo get_template_directory_uri() . '/js/libs/underscore-min.js' ?>"></script>
		<script type="text/javascript" src="<?php echo get_template_directory_uri() . '/js/libs/jquery.serializeJSON.min.js' ?>"></script>
		<script type="text/javascript" src="<?php echo get_template_directory_uri() . '/js/libs/moment-with-langs.min.js' ?>"></script>
		<script type="text/javascript" src="<?php echo get_template_directory_uri() . '/js/libs/backbone-min.js' ?>"></script>
		<script type="text/javascript" src="<?php echo get_template_directory_uri() . '/js/libs/backbone.marionette.min.js' ?>"></script>
		<script type="text/javascript" src="<?php echo get_template_directory_uri() . '/js/libs/bootstrap.min.js' ?>"></script>
		<script type="text/javascript" src="<?php echo get_template_directory_uri() . '/js/libs/jquery.nicescroll.min.js' ?>"></script>
		<link rel="stylesheet" href="<?php echo get_template_directory_uri() . '/css/bootstrap.min.css' ?>" />
		<link rel="stylesheet" href="<?php echo get_template_directory_uri() . '/css/font-awesome.min.css' ?>" />
		<link rel="stylesheet" href="<?php echo get_template_directory_uri() . '/css/normalize.css' ?>" />
		<link rel="stylesheet" href="<?php echo get_template_directory_uri() . '/style.css' ?>" />
		<script type="text/javascript">
			var base_url = '<?php echo get_site_url() ?>/api/';
			var _nonce = '<?php echo wp_create_nonce() ?>';
		</script>
		<?php wp_head(); ?>
	</head>
	<body>

		<div id="wrapper">

		</div>

		<!-- APPVIEW -->
		<script type="text/template" id="appview">
			<div id="modals"></div>			
			<aside id="sidebar">
				<div class="sidebar-nav">
					<span id="logo"></span>
					<a href="#" class="add-project btn btn-default pull-right"><i class="fa fa-plus"></i></a>

				</div>
				<div id="sidebar-content">
					<section id="projects"></section>
				</div>
				<div id="user-block">
					<?php echo get_current_user()->user_username ?>
					<a href="<?php echo wp_logout_url( site_url('/') ) ?>" class="btn btn-default"><i class="fa fa-power-off"></i></a>
				</div>
			</aside>

			<section id="content">
				<header id="top">
					<a href="#" class="show-sidebar btn btn-default pull-left"><i class="fa fa-bars"></i></a>
					<h1><a href="#" id="project-title" class="edit-project pull-left"></a></h1>
					<div class="pull-right">
						<div class="btn-group project-sections">
							<a href="#" class="section-messages btn btn-default"><i class="fa fa-comments"></i></a>
							<a href="#" class="section-events btn btn-default"><i class="fa fa-calendar"></i></a>
							<a href="#" class="section-files btn btn-default"><i class="fa fa-file"></i></a>
						</div>						
					</div>
					<div class="dropdown class-mobile-sections pull-right">
						<a href="#" data-toggle="dropdown" class="btn btn-default"><i class="fa fa-caret-down"></i></a>
						<ul class="dropdown-menu">
							<li><a href="#" class="section-messages">Messages</a></li>
							<li><a href="#" class="section-events">Events</a></li>
							<li><a href="#" class="section-files">Files</a></li>
						</ul>
					</div>
					<a href="#" class="btn btn-primary pull-right add-new-message"><i class="fa fa-plus"></i></a>
				</header>				
				<div id="content-holder"></div>
			</section>
		</script>
		<!-- // APPVIEW -->

		<script type="text/template" id="message-modal">
			<div class="modal-dialog">
				<div class="modal-content">
					<header class="modal-header">
						<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
						<h4>Message</h4>
					</header>
					<section class="modal-body">
						<form action="" name="message" id="message-form" method="post" onsubmit="return false">
							<input type="hidden" name="type" value="message" />
							<input type="hidden" name="parent_id" value="<%= parent_id %>" />
							<div class="form-group">
								<div id="message-editor-toolbar" style="display: none;">
									<a data-wysihtml5-command="bold" class="editor-command-button"><i class="fa fa-bold"></i></a>
									<a data-wysihtml5-command="italic" class="editor-command-button"><i class="fa fa-italic"></i></a>
									<a data-wysihtml5-command="insertUnorderedList" class="editor-command-button"><i class="fa fa-list"></i></a>

									<a data-wysihtml5-command="createLink" class="editor-command-button"><i class="fa fa-link"></i></a>
									<div data-wysihtml5-dialog="createLink" style="display: none;" class="editor-link-dialog">
										<div class="field">
											<label>Link:</label>
											<input data-wysihtml5-dialog-field="href" value="http://" type="text" class="editor-link-field form-control">
											<div class="editor-link-actions">
												<a data-wysihtml5-dialog-action="save" class="btn btn-success">OK</a> <a data-wysihtml5-dialog-action="cancel" class="btn btn-default">Cancel</a>
											</div>
										</div>		
									</div>
								</div>
								<textarea name="content" id="message-content" cols="30" rows="10" class="wysi-field form-control" placeholder="Message..."><%= content %></textarea>
							</div>
							<div class="form-group">
								<input type="text" name="meta[duedate]" value="<% if(meta.duedate) { %><%= moment(meta.duedate, 'YYYY-MM-DD').format('MMMM DD, YYYY') %><% } %>" placeholder="Set date" class="form-control datepicker" />
							</div>
							<div class="checkbox">
								<label for="meta[completed]">
									<input type="checkbox" name="meta[completed]" value="1" <% if(meta.completed == 1) {%><%= 'checked' %><% } %> />Done
								</label>
							</div>
						</form>
						<?php get_template_part( 'fileupload' ) ?>
					</section>
					<footer class="modal-footer">
						<a href="#" class="btn btn-success"><i class="fa fa-check"></i></a>
					</footer>
				</div>
			</div>
		</script>

		<script type="text/template" id="message-item" >
			<article class="message-wrapper">
				<div class="message">
					<div class="message-actions dropdown pull-right">
						<a href="#" data-toggle="dropdown" class="btn btn-default dropdown-toggle"><i class="fa fa-caret-down"></i></a>
						<ul class="dropdown-menu">
							<li><a href="#" class="action-addcomment">Add comment</a></li>
							<li><a href="#" class="action-edit">Edit</a></li>
							<% if(meta.duedate) { %>
							<% if(meta.completed != 1) { %>
							<li><a href="#" class="action-markdone">Mark as done</a></li>
							<% } else { %>
							<li><a href="#" class="action-markdone">Undone</a></li>
							<% } %>
							<li><a href="#" class="action-unfollow">Remove date</a></li>
							<% } %>
							<li><a href="#" class="action-delete">Delete message</a></li>
						</ul>
					</div>

					<% if(meta.duedate) { %>
					<% if(meta.completed == 1) { label = 'success'} else {label = 'danger' } %>
					<span class="label label-<%= label %> pull-right label-duedate"><%= moment(meta.duedate, "YYYY-MM-DD").format("MMMM DD, YYYY") %></span>
					<% } %>
					<span class="muted small pull-right"><%= moment(updated_on, "X").fromNow() %></span>

					<div class="avatar"><%= author.avatar %></div>
					<div class="message-content">
						<b><%= author.name %></b>
						<p><%= content %></p>

						<div class="message-files">
							<% _.each(files, function(file) { %>
							<a class="file-item thumbnail" target="_blank" href="<%= file.file_url %>">
								<img src="<%= file.file_thumb %>" /><br />
								<span class="file-name"><%= file.file_name %></span>
							</a>
							<% }) %>
						</div>
						<div class="message-comments">
							<% _.each(comments, function(comment) { %>
							<div class="well comment" data-id="<%= comment.id %>">
								<a href="#" class="delete pull-right muted" data-id="<%= comment.id %>"><i class="fa fa-times"></i></a>
								<b><%= comment.author %></b> - <%= moment(comment.date, "X").fromNow() %><br>
								<%= comment.comment %>
							</div>
							<% }) %>
						</div>						
					</div>
				</div>
			</article>	
		</script>

		<script type="text/template" id="project-modal">
			<div class="modal-dialog">
				<div class="modal-content">
					<form action="" method="post">
						<input type="hidden" name="type" value="project" />
						<header class="modal-header">
							<a href="#" class="close"><i class="fa fa-times"></i></a>
							<h4>Project</h4>
						</header>
						<section class="modal-body">
							<div class="form-group">
								<input type="text" name="title" value="<%= title %>" placeholder="Title" class="form-control" />
							</div>
							<div class="form-group">
								<div class="members"></div>
							</div>
						</section>
						<footer class="modal-footer">
							<a href="#" class="btn btn-danger pull-left"><i class="fa fa-times"></i></a>
							<a href="#" class="btn btn-success"><i class="fa fa-check"></i></a>
						</footer>
					</form>
				</div>
			</div>
		</script>

		<script type="text/template" id="comment-modal">
			<div class="modal-dialog">
				<div class="modal-content">
					<form action="">
						<header class="modal-header">
							<a href="#" class="close"><i class="fa fa-times"></i></a>
							<h4>Comment</h4>
						</header>
						<section class="modal-body">
							<div class="form-group">
								<div id="comment-editor-toolbar" style="display: none;">
									<a data-wysihtml5-command="bold" class="editor-command-button"><i class="fa fa-bold"></i></a>
									<a data-wysihtml5-command="italic" class="editor-command-button"><i class="fa fa-italic"></i></a>
									<a data-wysihtml5-command="insertUnorderedList" class="editor-command-button"><i class="fa fa-list"></i></a>

									<a data-wysihtml5-command="createLink" class="editor-command-button"><i class="fa fa-link"></i></a>
									<div data-wysihtml5-dialog="createLink" style="display: none;" class="editor-link-dialog">
										<div class="field">
											<label>Link:</label>
											<input data-wysihtml5-dialog-field="href" value="http://" type="text" class="editor-link-field form-control">
											<div class="editor-link-actions">
												<a data-wysihtml5-dialog-action="save" class="btn btn-success">OK</a> <a data-wysihtml5-dialog-action="cancel" class="btn btn-default">Cancel</a>
											</div>
										</div>		
									</div>
								</div>
								<textarea name="content" id="" cols="30" rows="10" class="form-control wysi-field"></textarea>
							</div>
						</section>
						<footer class="modal-footer">
							<a href="#" class="btn btn-success"><i class="fa fa-check"></i></a>
						</footer>
					</form>
				</div>
			</div>
		</script>

		<script type="text/template" id="project-item">
			<% if(meta.followup_count) { %><span class="followup-count badge"><%= meta.followup_count %></span><% } %>
			<% if(unread > 0) { %><span class="unread-badge badge"><%= unread %></span><% } %>
			<span class="project-item">
				<b><%= title %></b><br />
				<span class="muted small"><%= moment(updated_on, "X").fromNow() %></span>
			</span>
		</script>

		<script type="text/template" id="file-section-item">
			<% _.each(files, function(file) { %>
			<a href="<%= file.file_url %>" target="_blank" class="file-item thumbnail">
				<span class="delete badge" data-id="<%= file.id %>"><i class="fa fa-times"></i></span>
				<img src="<%= file.file_thumb %>" alt="" />
				<span class="file-name"><%= file.file_name %></span>
			</a>
			<% }) %>
		</script>

		<script type="text/template" id="file-item">
			<a href="<%= file_url %>" target="_blank" class="file-item thumbnail">
				<span class="delete badge"><i class="fa fa-times"></i></span>
				<img src="<%= file_thumb %>" alt="" />
				<span class="file-name"><%= file_name %></span>
			</a>
		</script>

		<script type="text/template" id="files-empty">
			<div class="text-center">
				<h1>No Files attached</h1>
			</div>
		</script>

		<script type="text/template" id="projects-empty">
			<div class="text-center">
				<h1>No Projects</h1>
				<p>Add one by clicking on the plus sign!</p>
			</div>
		</script>


		<script type="text/template" id="messages-empty">
			<div class="text-center">
				<h1>No messages</h1>
				<p>Add one by clicking on the plus sign!</p>
			</div>
		</script>

	</body>
</html>
