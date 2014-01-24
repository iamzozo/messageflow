$(function() {
	
	UserModel = Backbone.Model.extend({
		url: function() {
			return app.base_url + '?section=user&id=' + (this.get('id') ? this.get('id') : '')
		},
		defaults: {
			username : '',
			profile_image : '',
			email : '',
			first_name : '',
			last_name : ''
		}
	});		
	
	UserCollection = Backbone.Collection.extend({
		model: UserModel,
		url: app.base_url + '?section=users'
	});
	
	Users = new UserCollection();
	Users.fetch();

	ProjectModel = Backbone.Model.extend({
		url: app.base_url + '?post_type=project',
		initialize: function() {
			this.listenTo(this, 'destroy', this.del);
		},
		defaults: {
			title : '',
			active: false,
			meta: {
				followup_count : 0
			},
			unread: 0,
			is_favorite: false,
			updated_on: null
		}		
	})
	
	ProjectCollection = Backbone.Collection.extend({
		url : app.base_url +'?post_type=project',
		model: ProjectModel,
		setFollowup: function() {
			c = Messages.filter(function(i) {
				m = i.get('meta');
				if(typeof m.duedate != 'undefined' && m.duedate != '' && (typeof m.completed == 'undefined' || m.completed != 1)) {
					return i
				}
			})
			p = Projects.get(currentProject);
			m = p.get('meta');
			m.followup_count = c.length;
			p.save({				
				meta: m
			}, {
				wait: true,
				url: app.base_url + '?id=' + p.id
			});
		}
	})		
	
	Projects = new ProjectCollection();
	
	ProjectItemView = Backbone.Marionette.ItemView.extend({
		tagName: 'li',
		template: '#project-item',
		className: 'list-group-item',
		events: {
			'click .project-item' : 'changeProject'
		},
		modelEvents: {
			'change' : 'render'
		},
		onRender: function() {
			if(this.model.get('unread') != 0) {
				$(this.el).addClass('unread');
			}
			if(this.model.get('active')) {
				$('#projects ul li').removeClass('active');
				$(this.el).addClass('active');
			}
			$(this.el).attr('id', 'project-' + this.model.get('id'))
		},
		changeProject: function() {
			currentProject = this.model.get('id');
			App.showMessages();
			Projects.each(function(p) {
				p.set('active', false);
			})
			this.model.set('active', true)
			$('body').removeClass('sidebar-opened');
			$('#project-title').text(this.model.get('title'));
			return false;
		}
	})
	
	ProjectEmptyView = Backbone.Marionette.ItemView.extend({
		className: 'projects-empty',
		template: '#projects-empty'
	})
	
	ProjectCollectionView = Backbone.Marionette.CollectionView.extend({
		tagName: 'ul',
		className: 'list-group',
		itemView: ProjectItemView,
		emptyView: ProjectEmptyView
	})
	
	ProjectModalView = Backbone.Marionette.ItemView.extend({
		template: '#project-modal',
		className: 'modal fade',
		initialize: function() {
			this.listenTo(Users, 'add', this.render)
		},
		events: {
			'click .btn-success' : 'save',
			'click .close' : 'hide',
			'click .btn-danger' : 'del'
		},
		save: function(e) {
			data = $(this.el).find('form').serializeJSON();
			var isnew = false
			if(this.model.isNew()) isnew = true;
			this.model.save(data, {
				wait: true,
				success: function(m) {
					AppRouter.navigate('projects/' + m.id + '/messages', {
						trigger:true
					})
					currentProject = m.id;
					if(isnew) Projects.add(m);
				}
			});			
			this.render();
			this.hide();
			e.preventDefault();
		},
		show: function() {
			$(this.el).modal('show');
		},
		hide: function() {
			$(this.el).modal('hide');
			return false;
		},		
		del: function(e) {
			this.model.destroy({
				url: app.base_url + '?id=' + this.model.get('id')
			});
			this.hide();
			e.preventDefault();
		},
		onRender: function() {
			_this = this;
			members = '';
			current_users = this.model.get('meta').users;
			Users.each(function(user) {
				checked = '';
				if($.inArray(user.id.toString(), current_users) != -1) {
					checked = 'checked'
				}
				$(_this.el).find('.members').append('<div class="checkbox"><label><input type="checkbox" name="meta[users][]" value="' + user.id + '" ' + checked + ' /> ' + user.get('username') + ' <br /><span class="muted">' + user.get('email') + '</span></label></div>');
			})
		}
	})

	ProjectModal = new ProjectModalView({
		model: new ProjectModel()
	})
	
	MessageModel = Backbone.Model.extend({
		url : function() {
			return app.base_url + '?id=' + (this.get('id') ? this.get('id') : '')
		},
		defaults: {
			date : '',
			content : '',
			updated_on : '',
			meta : new Array(),
			author : '',
			avatar : '',
			parent_id : 0,
			files : new Array(),
			comments: new Array()
		}
	})
	
	MessageView = Backbone.Marionette.ItemView.extend({
		template: '#message-item',
		initialize: function() {
			this.listenTo(this.model, 'destroy', this.remove)
			this.listenTo(this.model, 'change', this.render)
		},
		events: {
			'click .action-delete' : 'del',
			'click .action-edit' : 'edit',
			'click .action-unfollow' : 'unFollow',
			'click .action-complete' : 'markComplete',
			'click .action-addcomment' : 'addComment',
			'click .action-markdone' : 'toggleComplete',
			'click .action-undone' : 'toggleComplete',
			'click .comment .delete' : 'deleteComment'
		},		
		deleteComment: function(e) {
			id = $(e.currentTarget).data('id');
			comments = this.model.get('comments');
			_comment = '';
			filtered_comments = _.filter(comments, function(comment) {
				if(comment.id != id) return comment;
				else _comment = comment;
			})
			this.model.set('comments', filtered_comments);
			c = new CommentModel(_comment);
			c.destroy({
				url: app.base_url + '?section=comment&id=' + _comment.id
			});
			return false;
		},
		addComment: function() {
			CommentModal.model = new CommentModel({
				message_id: this.model.get('id')
			})
			CommentModal.render();
			CommentModal.show();
			return false;
		},
		edit: function() {
			MessageModal.model = this.model;
			MessageModal.render();
			MessageModal.show();
			return false;
		},
		unFollow: function(e) {			
			this.model.set({
				meta: {
					duedate : null,
					completed: null
				}
			});
			this.model.save();
			e.preventDefault();
		},
		toggleComplete: function() {
			m = this.model.get('meta');
			if(m.completed == 1) {
				m.completed = null;				
			} else {				
				m.completed = true;
			}
			this.model.save({
				meta: m
			});			
			this.model.trigger('change');
			return false;
		},
		del: function() {
			this.model.destroy({
				url: app.base_url + '?id=' + this.model.get('id')
			});
			Messages.trigger('change');
			return false;
		}		
	})

	MessageCollection = Backbone.Collection.extend({
		model: MessageModel,		
		initialize: function() {
			this.listenTo(this, 'change', Projects.setFollowup)
		},		
		url: function() {
			return app.base_url + '?post_type=message&posts_per_page=-1'
		},
		comparator: function(model) {
			return model.get('id');
		}		
	})
	
	Messages = new MessageCollection();
	
	MessageEmptyView = Backbone.Marionette.ItemView.extend({
		template: '#messages-empty'
	})	
	
	MessageCollectionView = Backbone.Marionette.CollectionView.extend({
		itemView: MessageView,		
		initialize: function() {
			this.listenTo(Projects, 'reset', this.render)
		},
		emptyView: MessageEmptyView,
		appendHtml: function(cv, iv){
			cv.$el.prepend(iv.el);
		}
	})
	
	MessageModalView = Backbone.Marionette.ItemView.extend({
		template : '#message-modal',
		className: 'modal fade',
		modelEvents: {
			'change' : 'render'
		},
		events: {
			'click .btn-success' : 'save',
			'click .close' : 'close_modal',
			'click .delete-file' : 'deleteFile'
		},
		save: function() {
			data = $(this.el).find('#message-form').serializeJSON();
			data.parent_id = currentProject;
			if( ! data.meta.completed) data.meta.completed = null;
			data.files = this.model.get('files');
			this.model.save(data, {
				wait: true
			});
			
			if(this.model.isNew()) Messages.add(this.model);
			
			this.hide();
			return false;
		},
		show: function() {
			$(this.el).modal('show');
		},
		hide: function() {
			$(this.el).modal('hide');
		},
		onRender: function() {
			
			_this = this;
			$(this.el).find('.datepicker').datepicker({
				dateFormat : 'MM d, yy'
			});				
			
			var editor = new wysihtml5.Editor($(this.el).find('.wysi-field').get(0), {
				toolbar:      "message-editor-toolbar",
				parserRules:  wysihtml5ParserRules
			});
			
			if(this.model.isNew()) this.model.set('files', new Array());

			_.each(this.model.get('files'), function(file) {
				f = new FileModel(file);
				f.set({
					message_id : _this.model.get('id')
				})
				$(_this.el).find('#fileholder').append(new FileItemView({
					model: f
				}).render().el);
			})
			$(this.el).find('#fileupload').fileupload({
				dataType: 'html',
				url : app.base_url + '?section=file&id=' + this.model.get('id'),
				maxNumberOfFiles: 1,
				done: function (e, data) {
					current_files = _this.model.get('files');
					if( ! current_files) current_files = new Array();
					result = $.parseJSON(data.result);
					current_files.push(result)
					_this.model.set({
						files: current_files
					})				
							
					// Update loading view with the result
					f = new FileModel();
					f.set($.parseJSON(data.result));
					$('#fileholder').append(new FileItemView({
						model: f
					}).render().el);
					$(_this.el).find('.loading').hide();
		
				},
				dragover: function() {
					$(this).addClass('over');
				},
				add: function(e, data) {
					$.each(data.files, function (index, file) {						
						$(_this.el).find('.loading').show();
					});
					data.submit();
				},
				dropZone: $('#fileupload'),
				drop: function(e, data) {
					$(this).removeClass('over')
				}
			})
		}
	})
	
	var MessageModal = new MessageModalView({
		model: new MessageModel()
	});
	
	CommentModel = Backbone.Model.extend({
		url : function() {
			return app.base_url + '?section=comment'
		}
	})
	
	CommentModalView = Backbone.Marionette.ItemView.extend({
		template : '#comment-modal',
		className: 'modal fade',
		events: {
			'click .btn-success' : 'save',
			'click .close' : 'hide'
		},
		show: function() {
			$(this.el).modal('show');
		},
		hide: function() {
			$(this.el).modal('hide');
		},
		save: function() {
			_mid = this.model.get('message_id');
			data = $(this.el).find('form').serializeJSON();
			data.object_id = _mid;
			_this = this;
			this.model.save(data, {
				success: function(m,r) {
					comments = Messages.get(_mid).get('comments');
					if( ! comments) comments = new Array();
					comments.push(r);
					Messages.get(_mid).set('comments',comments);
					Messages.get(_mid).trigger('change');
					_this.hide();					
				}
			});
			return false;
		},
		onRender: function() {
			var editor = new wysihtml5.Editor($(this.el).find('.wysi-field').get(0), {
				toolbar:      "comment-editor-toolbar",
				parserRules:  wysihtml5ParserRules
			});
		}
	});
	
	var CommentModal = new CommentModalView({
		model: new CommentModel()
	})
	
	EventsView = Backbone.Marionette.ItemView.extend({
		template: _.template('<div></div>'),
		initialize: function() {			
			this.listenTo(this.model, 'change create', this.render)
		},
		onRender: function() {
			_events = new Array();
			this.model.each(function(message) {
				if(message.get('meta').duedate) {
					_events.push({
						id: message.get('id'),
						title: $('<b>' + message.get('content') + '</b>').text().substr(0, 20),
						start: $.fullCalendar.parseDate(message.get('meta').duedate),
						color: message.get('meta').completed != 1 ? '#C36' : '#00CC99'
					})
				}
			})

			_this = this;
			$(this.el).fullCalendar({
				events : _events,
				editable: true,
				firstDay: 0,
				eventDrop: function( e, d, minuteDelta, allDay, revertFunc, jsEvent, ui, view ) {
					Messages.get(e.id).save({
						meta : {
							duedate : e.start
						}
					});
				},
				eventRender: function (e, element) {
					element.find('span.fc-event-title').text(e.title);
				},
				eventClick: function(e) {
					MessageModal.model = Messages.get(e.id);
					MessageModal.render();
					MessageModal.show();
				}
			});
			$(document).trigger('resize');
		}
	})
	
	FileCollection = Backbone.Collection.extend({
		url: function() {
			return base_url + 'files2/' + currentProject
		}
	})
	
	FileItemView = Backbone.Marionette.ItemView.extend({
		template: '#file-item',
		className: 'file-item-wrapper',
		events: {
			'click .delete' : 'del'
		},
		del: function() {
			this.model.destroy({
				url: app.base_url + '?section=file&id=' + this.model.get('id')
			})
			this.remove();
			return false;

		}
	})
	
	FileSectionView = Backbone.Marionette.ItemView.extend({
		template: '#file-section-item',
		events: {
			'click .delete' : 'del'
		},
		del: function(e) {
			id = $(e.currentTarget).data('id');
			f = new FileModel({
				id: id
			});
			f.destroy({
				url: app.base_url + '?section=file&id=' + id
			})
			files = _.filter(this.model.get('files'), function(file) {
				return file.id != id
			})
			this.model.set('files', files, {
				silent: true
			});
			this.render();
			return false;
		}
	})
	
	FileEmptyView = Backbone.Marionette.ItemView.extend({
		template: '#files-empty'
	})
	
	FileCollectionView = Backbone.Marionette.CollectionView.extend({
		className: 'files-wrapper',
		initialize: function() {
			this.listenTo(Messages, 'change', this.render);
		},
		itemView: FileSectionView,
		emptyView: FileEmptyView
	})
	
	FileModel = Backbone.Model.extend({
		urlRoot: base_url + 'files2'
	});
	
	
			
	AppLayout = Backbone.Marionette.Layout.extend({
		template : '#appview',
		initialize: function() {
			this.listenTo(Projects, 'reset add destroy', this.setProjects);
			this.listenTo(Projects, 'reset', this.setRead);
			$('#modals').append(MessageModal.render().el)
			$('#modals').append(ProjectModal.render().el)
			$('#modals').append(CommentModal.render().el)	
			this.showProjects();
		},
		regions: {
			content: '#content-holder',
			modals: '#modals',
			projects : '#projects'
		},
		events: {
			'click .section-projects' : 'showProjects',
			'click .section-messages' : 'showMessages',
			'click .section-events' : 'showEvents',
			'click .section-files' : 'showFiles',
			'click .add-new-message' : 'addMessage',
			'click .add-project' : 'addProject',
			'click .edit-project' : 'editProject',
			'click .show-sidebar' : 'showSidebar'
		},		
		setProjects: function() {	
			if(Projects.length > 0) {
				if( !currentProject) {
					p = Projects.first();
					currentProject = p.id;
				}
				else {
					p = Projects.get(currentProject)
					
				}
				AppRouter.navigate('projects/' + p.id + '/messages', {trigger: true});
				p.set('active', true);										
				title = p.get('title')
				$('#project-title').text(title);
				$('a.add-new-message, .project-sections').show();
			} else {				
				AppRouter.navigate('/', {trigger: true});
				$('a.add-new-message, .project-sections').hide();
				$('#project-title').text('');
				App.content.close();
			}	
		},
		setRead: function() {
			if(Projects.length > 0) {
				Projects.get(currentProject).set('unread', 0);	
			}
		},

		showSidebar : function() {
			$('body').toggleClass('sidebar-opened');
			return false;
		},
		showFiles: function() {
			$('.project-sections a').removeClass('active');
			$('a.section-files').addClass('active');
			if( ! Messages.fetched || Messages.project_id != currentProject) {
				App.fetchMessages('showFiles');
				return false;
			}
			Files = Messages.filter(function(m) {
				f = m.get('files');
				if(f && f.length > 0) {
					return m
				}
			})
			FileCollection = new MessageCollection(Files);
			App.content.show(new FileCollectionView({
				collection: FileCollection
			}))
			AppRouter.navigate('projects/' + currentProject + '/files');
			return false;
		},
		showEvents: function() {
			$('.project-sections a').removeClass('active');
			$('a.section-events').addClass('active');
			if( ! Messages.fetched || Messages.project_id != currentProject) {
				App.fetchMessages('showEvents');
				return false;
			}
			App.content.show(new EventsView({
				model: Messages
			}))
			AppRouter.navigate('projects/' + currentProject + '/events');
			return false;
		},
		showMessages: function() {
			$('.project-sections a').removeClass('active');
			$('a.section-messages').addClass('active');
			if( ! Messages.fetched || Messages.project_id != currentProject) {
				App.fetchMessages('showMessages');
				return false;
			}
			App.content.show(new MessageCollectionView({
				collection: Messages
			}))
			AppRouter.navigate('projects/' + currentProject + '/messages');
			return false;
		},
		fetchMessages: function(callback) {
			Messages.fetch({
				reset: true,
				data: {
					post_parent: currentProject
				},
				success: function() {
					Messages.fetched = true;
					Messages.project_id = currentProject;
					App[callback]();
				}
			})
		},
		showProjects: function() {
			Projects.fetch({
				reset: true,
				success: function() {
					App.projects.show(new ProjectCollectionView({
						collection: Projects
					}));
				}
			})
			return false;
		},
		addMessage: function(e) {
			MessageModal.model = new MessageModel();
			MessageModal.render();
			MessageModal.show();
			e.preventDefault();
		},
		addProject: function() {
			ProjectModal.model = new ProjectModel();
			ProjectModal.render();
			ProjectModal.show();
			return false;
		},
		editProject: function() {
			ProjectModal.model = Projects.get(currentProject);
			ProjectModal.render();
			ProjectModal.show();
			return false;
		},
		onRender: function() {
			$(this.el).find('#sidebar-content').niceScroll( {
				cursorcolor : '#FFF',
				autohidemode: false,
				horizrailenabled: false,
				cursoropacitymax: .2,
				cursorwidth: 6
			});
		}
	})
	var App = new AppLayout();	
	
	$('#wrapper').html(App.render().el);
	
	var currentProject;
	
	Router = Backbone.Router.extend({
		routes : {
			'' : 'projects',
			'projects/:query/:section' : 'projects'
		},
		projects: function(id, section) {
			currentProject = id;			
			switch(section) {
				case 'messages' :
					App.showMessages(id);
					break;
				case 'events' :
					App.showEvents(id);
					break;
				case 'files' :
					App.showFiles(id);
					break;
			}
		}		
	})
	
	AppRouter = new Router();
	Backbone.history.start();
})